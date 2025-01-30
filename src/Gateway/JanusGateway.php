<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Config\Config;
use App\Exceptions\GatewayException;
use App\Logs\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class JanusGateway
{
    private Logger $logger;
    private Config $config;
    private string $baseUrl;
    private string $apiSecret;
    private Client $client;

    public function __construct()
    {
        $this->logger = Logger::getInstance('janus-gateway');
        $this->config = Config::getInstance();
        $this->baseUrl = $this->config->get('JANUS_HTTP_ENDPOINT', 'http://127.0.0.1:8088/janus');
        $this->apiSecret = $this->config->get('JANUS_API_SECRET', 'janusrocks');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 10.0,
            'connect_timeout' => 5.0,
            'http_errors' => false,
            'verify' => false,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * 发送请求到 Janus 服务器
     */
    public function sendRequest(string $endpoint, array $data): array
    {
        try {
            // 添加 API Secret
            $data['apisecret'] = $this->apiSecret;

            // 如果没有 transaction，添加一个
            if (!isset($data['transaction'])) {
                $data['transaction'] = $this->generateTransactionId();
            }

            // 确保 endpoint 以 / 开头
            $endpoint = ltrim($endpoint, '/');
            $url = $endpoint ? "{$this->baseUrl}/{$endpoint}" : $this->baseUrl;

            $this->logger->debug("Sending request to Janus", [
                'url' => $url,
                'data' => $data
            ]);

            // 对于 trickle 请求使用较短的超时时间
            $options = [
                'json' => $data,
            ];

            if (str_contains($endpoint, '/trickle')) {
                $options['timeout'] = 2.0; // trickle 请求使用更短的超时时间
            }

            $response = $this->client->post($url, $options);

            // 检查状态码
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new GatewayException("Janus returned non-200 status code: $statusCode");
            }

            $contents = $response->getBody()->getContents();

            // 特殊处理 trickle 请求
            if (str_contains($endpoint, '/trickle')) {
                // 如果是空响应或者响应体很小，都认为是正常的
                if (empty($contents) || strlen($contents) < 5) {
                    return [
                        'janus' => 'ack',
                        'transaction' => $data['transaction']
                    ];
                }
            }

            $result = json_decode($contents, true);

            if (!$result && !str_contains($endpoint, '/trickle')) {
                throw new \Exception('Invalid response from Janus server');
            }

            return $result ?: [];
        } catch (GuzzleException $e) {
            $this->logger->error("Failed to communicate with Janus", [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
                'data' => $data
            ]);

            // 对于 trickle 请求的特殊处理
            if (str_contains($endpoint, '/trickle')) {
                if (
                    str_contains($e->getMessage(), 'Empty reply from server') ||
                    str_contains($e->getMessage(), 'Operation timed out')
                ) {
                    return [
                        'janus' => 'ack',
                        'transaction' => $data['transaction']
                    ];
                }
            }

            throw new GatewayException("Failed to communicate with Janus: " . $e->getMessage());
        }
    }
    /**
     * 创建 Janus 会话
     */
    public function createSession(): array
    {
        return $this->sendRequest('', [
            'janus' => 'create',
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 附加到 AudioBridge 插件
     */
    public function attachPlugin(string $sessionId): array
    {
        // 确保正确的路径格式
        return $this->sendRequest("$sessionId", [
            'janus' => 'attach',
            'plugin' => 'janus.plugin.audiobridge',
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 创建音频房间
     */
    public function createRoom(string $sessionId, string $handleId, array $config): array
    {
        if (empty($config['roomId']) || !is_numeric($config['roomId'])) {
            throw new GatewayException('Room ID must be a positive integer');
        }

        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'create',
                'room' => (int)$config['roomId'],
                'description' => $config['description'] ?? '',
                'sampling_rate' => $config['sampling_rate'] ?? 16000,
                'spatial_audio' => $config['spatial_audio'] ?? false,
                'record' => false,
                'permanent' => false
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 修复 trickle 请求路径
     */
    public function sendTrickle(string $sessionId, string $handleId, array $candidate): array
    {
        // 注意这里不要加 /trickle
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'trickle',
            'candidate' => $candidate,
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 生成事务ID
     */
    private function generateTransactionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function joinRoom(string $sessionId, string $handleId, int $roomId, string $display): array
    {
        // 修复 URL 构造
        return $this->sendRequest("$sessionId/$handleId", [
            "janus" => "message",
            "body" => [
                "request" => "join",
                "room" => $roomId,
                "display" => $display,
                "muted" => false,
            ],
            "transaction" => $this->generateTransactionId()
        ]);
    }
}
