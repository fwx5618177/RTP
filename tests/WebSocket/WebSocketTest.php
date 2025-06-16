<?php

namespace Tests\WebSocket;

use App\Config\Config;
use App\Logs\Logger;
use App\Services\WebSocketService;
use GuzzleHttp\Client as HttpClient;
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client as SwooleClient;

class WebSocketTest extends TestCase
{
    private WebSocketService $wsService;
    private string $wsHost;
    private int $wsPort;
    private Logger $logger;
    private string $connectionId;
    private HttpClient $httpClient;
    private string $apiBaseUrl;

    protected function setUp(): void
    {
        $this->wsService = new WebSocketService();
        $this->logger = Logger::getInstance('websocket-test');

        $config = Config::getInstance();
        $this->wsHost = $config->get('WS_HOST', '127.0.0.1');
        $this->wsPort = (int)$config->get('WS_PORT', 9502);
        $this->apiBaseUrl = $config->get('API_BASE_URL', 'http://localhost:9501');

        $this->httpClient = new HttpClient([
            'base_uri' => $this->apiBaseUrl,
            'timeout' => 5.0,
        ]);

        $this->connectionId = $this->wsService->generateConnectionId();

        $this->logger->info('WebSocket Test Setup', [
            'host' => $this->wsHost,
            'port' => $this->wsPort,
        ]);
    }

    /**
     * 创建已认证的 WebSocket 客户端
     */
    private function createAuthenticatedClient(): SwooleClient
    {
        // 1. 获取连接信息
        $response = $this->httpClient->get('/api/ws/connect', [
            'query' => ['token' => 'test_token_123']
        ]);

        $connectionInfo = json_decode($response->getBody()->getContents(), true);

        $this->logger->info('Connection info', $connectionInfo);

        // 2. 准备握手信息
        $handshake = $this->wsService->generateHandshake(
            $connectionInfo['data']['client_id'],
            $connectionInfo['data']['token']
        );

        // 3. 创建客户端
        $client = new SwooleClient($this->wsHost, $this->wsPort);

        // 4. 设置握手头信息
        $client->setHeaders([
            'X-Connection-Id' => $connectionInfo['data']['client_id'],
            'X-Handshake-Token' => $connectionInfo['data']['token'],
            'X-Handshake-Timestamp' => (string)$handshake['timestamp'],
            'X-Handshake-Signature' => $handshake['signature'],
            'User-Agent' => 'WebSocket-Test-Client'
        ]);

        // 5. 升级连接
        $ret = $client->upgrade('/ws/' . $connectionInfo['data']['client_id']);
        $this->assertTrue($ret, 'WebSocket upgrade failed');

        return $client;
    }

    public function testWebSocketConnection(): void
    {
        Coroutine\run(function () {
            try {
                $client = $this->createAuthenticatedClient();
                $this->logger->info('WebSocket connection established');

                // 测试基本的 ping/pong
                $this->logger->info('Testing ping message');
                $client->push('ping');

                $response = $client->recv();
                $this->assertNotEmpty($response, 'No response received from server');
                $this->assertEquals('pong', $response->data);

                $client->close();
                $this->logger->info('WebSocket connection closed successfully');
            } catch (\Exception $e) {
                $this->logger->error('WebSocket test failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    public function testWebSocketAuthentication(): void
    {
        Coroutine\run(function () {
            try {
                // 测试无效的连接 ID
                $this->logger->info('Testing invalid connection ID');
                $invalidClient = new SwooleClient($this->wsHost, $this->wsPort);
                $invalidClient->setHeaders([
                    'X-Connection-Id' => 'invalid_id',
                    'X-Handshake-Token' => 'test_token_123',
                    'X-Handshake-Timestamp' => (string)time(),
                    'X-Handshake-Signature' => 'test_signature'
                ]);

                $this->logger->debug('Attempting connection with invalid ID', [
                    'url' => "ws://{$this->wsHost}:{$this->wsPort}/ws/invalid_id"
                ]);

                // 尝试升级连接
                $result = $invalidClient->upgrade('/ws/invalid_id');

                if ($result) {
                    $response = $invalidClient->recv(2.0); // 添加超时时间
                    $this->assertNotNull($response, 'No response received from server');

                    if ($response) {
                        $this->logger->debug('Received response', ['data' => $response->data]);
                        $responseData = json_decode($response->data, true);

                        $this->assertNotNull($responseData, 'Failed to decode response JSON');
                        $this->assertArrayHasKey('error', $responseData);
                        $this->assertStringContainsString('Invalid', $responseData['error']);
                    }
                } else {
                    // 如果连接失败也是符合预期的
                    $this->logger->info('Connection failed as expected');
                }

                $invalidClient->close();

                // 测试无效的握手信息
                $this->logger->info('Testing invalid handshake');
                $invalidHandshakeClient = new SwooleClient($this->wsHost, $this->wsPort);

                // 使用有效的连接ID但无效的握手信息
                $validConnectionId = $this->wsService->generateConnectionId();
                $invalidHandshakeClient->setHeaders([
                    'X-Connection-Id' => $validConnectionId,
                    'X-Handshake-Token' => 'invalid_token',
                    'X-Handshake-Timestamp' => (string)time(),
                    'X-Handshake-Signature' => 'invalid_signature'
                ]);

                $this->logger->debug('Attempting connection with invalid handshake');

                $result = $invalidHandshakeClient->upgrade('/ws/' . $validConnectionId);

                if ($result) {
                    $response = $invalidHandshakeClient->recv(2.0); // 添加超时时间
                    $this->assertNotNull($response, 'No response received from server');

                    if ($response) {
                        $this->logger->debug('Received response', ['data' => $response->data]);
                        $responseData = json_decode($response->data, true);

                        $this->assertNotNull($responseData, 'Failed to decode response JSON');
                        $this->assertArrayHasKey('error', $responseData);
                        $this->assertStringContainsString('Invalid', $responseData['error']);
                    }
                } else {
                    // 如果连接失败也是符合预期的
                    $this->logger->info('Connection failed as expected');
                }

                $invalidHandshakeClient->close();
            } catch (\Exception $e) {
                $this->logger->error('Authentication test failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    public function testMessageRoute(): void
    {
        Coroutine\run(function () {
            try {
                $client = $this->createAuthenticatedClient();
                $this->logger->info('WebSocket connection established for message test');

                // 测试普通消息
                $messageData = json_encode([
                    'route' => '/message',
                    'content' => 'Hello World'
                ]);

                $this->logger->debug('Sending message', ['data' => $messageData]);
                $client->push($messageData);

                // 添加超时时间并检查响应
                $response = $client->recv(2.0);
                $this->assertNotNull($response, 'No response received from server');
                $this->assertNotNull($response->data, 'Response data is null');

                $this->logger->debug('Received response', ['data' => $response->data]);
                $responseData = json_decode($response->data, true);

                // 检查 JSON 解码是否成功
                $this->assertNotNull($responseData, 'Failed to decode response JSON');
                $this->assertIsArray($responseData, 'Response is not a valid JSON array');


                // 检查响应结构
                $this->assertArrayHasKey('status', $responseData, 'Response missing status field');
                $this->assertArrayHasKey('content', $responseData, 'Response missing content field');

                // 验证响应内容
                $this->assertEquals('success', $responseData['status']);
                $this->assertEquals('Hello World', $responseData['content']);

                $client->close();
                $this->logger->info('Message route test completed successfully');
            } catch (\Exception $e) {
                $this->logger->error('Message route test failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    public function testBroadcastRoute(): void
    {
        Coroutine\run(function () {
            try {
                // 创建两个客户端
                $client1 = $this->createAuthenticatedClient();
                $client2 = $this->createAuthenticatedClient();

                // 发送广播消息
                $broadcastData = json_encode([
                    'route' => '/broadcast',
                    'message' => 'Broadcast test message'
                ]);
                $client1->push($broadcastData);

                // 验证发送者收到确认
                $response1 = $client1->recv();
                $responseData1 = json_decode($response1->data, true);
                $this->assertEquals('success', $responseData1['status']);

                // 验证接收者收到广播
                $response2 = $client2->recv();
                $responseData2 = json_decode($response2->data, true);
                $this->assertEquals('broadcast', $responseData2['type']);
                $this->assertEquals('Broadcast test message', $responseData2['message']);

                $client1->close();
                $client2->close();
            } catch (\Exception $e) {
                $this->logger->error('Broadcast route test failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    public function testPrivateMessageRoute(): void
    {
        Coroutine\run(function () {
            try {
                // 创建两个客户端模拟私聊
                $client1 = $this->createAuthenticatedClient();
                $client2 = $this->createAuthenticatedClient();
                // 获取客户端2的连接ID
                $client2Info = $client2->socket->fd;

                // 发送私聊消息
                $privateData = json_encode([
                    'route' => '/private',
                    'to' => $client2Info,
                    'message' => 'Private test message'
                ]);
                $client1->push($privateData);

                // 验证发送者收到确认
                $response1 = $client1->recv();
                $responseData1 = json_decode($response1->data, true);
                $this->assertEquals('success', $responseData1['status']);

                // 验证接收者收到私聊消息
                $response2 = $client2->recv();
                $responseData2 = json_decode($response2->data, true);
                $this->assertEquals('private', $responseData2['type']);
                $this->assertEquals('Private test message', $responseData2['message']);

                $client1->close();
                $client2->close();
            } catch (\Exception $e) {
                $this->logger->error('Private message route test failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    public function testInvalidRoute(): void
    {
        Coroutine\run(function () {
            try {
                $client = $this->createAuthenticatedClient();

                // 测试无效路由
                $invalidData = json_encode([
                    'route' => '/invalid_route',
                    'content' => 'test'
                ]);
                $client->push($invalidData);

                $response = $client->recv();
                $responseData = json_decode($response->data, true);

                $this->assertArrayHasKey('error', $responseData);
                $this->assertEquals('Invalid route', $responseData['error']);

                $client->close();
            } catch (\Exception $e) {
                $this->logger->error('Invalid route test failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }

    protected function tearDown(): void
    {
        $this->logger->info('WebSocket test completed');
    }
}
