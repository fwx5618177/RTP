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

    public function testWebSocketConnection(): void
    {
        // 使用 Swoole 协程运行测试
        Coroutine\run(function () {
            try {
                // 1. 获取连接信息
                $response = $this->httpClient->get('/api/ws/connect', [
                    'query' => ['token' => 'test_token_123'],
                ]);

                $connectionInfo = json_decode($response->getBody()->getContents(), true);
                $this->assertNotEmpty($connectionInfo['data']['websocket_url'], 'WebSocket URL should not be empty');

                // 2. 准备握手信息
                $handshake = $this->wsService->generateHandshake(
                    $connectionInfo['data']['connection_id'],
                    $connectionInfo['data']['token']
                );

                // 3. 创建 Swoole WebSocket 客户端
                $client = new SwooleClient($this->wsHost, $this->wsPort);

                // 4. 设置握手头信息
                $client->setHeaders([
                    'X-Connection-Id' => $connectionInfo['data']['connection_id'],
                    'X-Handshake-Token' => $connectionInfo['data']['token'],
                    'X-Handshake-Timestamp' => (string)$handshake['timestamp'],
                    'X-Handshake-Signature' => $handshake['signature'],
                    'User-Agent' => 'WebSocket-Test-Client',
                ]);

                $this->logger->debug('Connecting to WebSocket', [
                    'url' => $connectionInfo['data']['websocket_url'],
                    'headers' => $client->requestHeaders,
                ]);

                // 5. 升级连接到 WebSocket
                $ret = $client->upgrade('/ws/' . $connectionInfo['data']['connection_id']);
                $this->assertTrue($ret, 'WebSocket upgrade failed');
                $this->logger->info('WebSocket connection established');

                // 6. 测试 ping/pong
                Coroutine::sleep(0.5); // 等待连接稳定
                $this->logger->info('Testing ping message');
                $client->push('ping');

                $response = $client->recv();
                $this->logger->info('Received response', ['response' => $response]);
                $this->assertNotEmpty($response, 'No response received from server');
                $this->assertEquals('pong', $response->data);
                $this->logger->info('WebSocket test passed', ['data' => $response->data]);

                $client->close();
                $this->logger->info('WebSocket connection closed successfully');
            } catch (\Exception $e) {
                $this->logger->error('WebSocket test failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }
        });
    }

    public function testWebSocketAuthentication(): void
    {
        // 使用 Swoole 协程运行测试
        Coroutine\run(function () {
            $connectionId = $this->wsService->generateConnectionId();
            $wsUrl = "ws://{$this->wsHost}:{$this->wsPort}/ws/{$connectionId}";

            // 测试无效的连接 ID
            $this->logger->info('Testing invalid connection ID');
            $invalidClient = new SwooleClient($this->wsHost, $this->wsPort);
            $invalidClient->setHeaders([
                'X-Connection-Id' => 'invalid_id',
                'X-Handshake-Token' => 'test_token_123',
            ]);

            $this->logger->debug('Attempting connection with invalid ID', [
                'url' => "ws://{$this->wsHost}:{$this->wsPort}/ws/invalid_id",
            ]);

            $result = $invalidClient->upgrade('/ws/invalid_id');
            $this->assertEquals($result, 'Connection should fail with invalid connection ID');
            $invalidClient->close();

            // 测试无效的握手信息
            $this->logger->info('Testing invalid handshake');
            $invalidHandshakeClient = new SwooleClient($this->wsHost, $this->wsPort);
            $invalidHandshakeClient->setHeaders([
                'X-Connection-Id' => $connectionId,
                'X-Handshake-Token' => 'invalid_token',
                'X-Handshake-Timestamp' => (string)time(),
                'X-Handshake-Signature' => 'invalid_signature',
            ]);

            $this->logger->debug('Attempting connection with invalid handshake', [
                'url' => $wsUrl,
            ]);

            $result = $invalidHandshakeClient->upgrade('/ws/' . $connectionId);
            $this->assertEquals($result, 'Connection should fail with invalid handshake');
            $invalidHandshakeClient->close();
        });
    }

    protected function tearDown(): void
    {
        $this->logger->info('WebSocket test completed');
    }
}
