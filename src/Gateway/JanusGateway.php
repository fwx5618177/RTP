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
            'timeout' => 5.0,
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

            // 确保 endpoint 以 / 开头
            $endpoint = ltrim($endpoint, '/');
            $url = $endpoint ? "{$this->baseUrl}/{$endpoint}" : $this->baseUrl;

            $this->logger->debug("Sending request to Janus", [
                'url' => $url,
                'data' => $data
            ]);

            $response = $this->client->post($url, [
                'json' => $data
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (!$result) {
                throw new \Exception('Invalid response from Janus server');
            }

            return $result;
        } catch (GuzzleException $e) {
            $this->logger->error("Janus request failed", [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
                'data' => $data
            ]);
            throw new \Exception('Failed to communicate with Janus: ' . $e->getMessage());
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
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'create',
                'room' => $config['roomId'],
                'description' => $config['description'] ?? '',
                'sampling_rate' => $config['sampling_rate'] ?? 16000,
                'spatial_audio' => $config['spatial_audio'] ?? false,
            ],
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
