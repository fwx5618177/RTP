<?php

namespace App\Services;

use App\Config\Config;
use App\Logs\Logger;
use App\Exceptions\ValidationException;

class WebSocketService
{
    private Config $config;
    private Logger $logger;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance('websocket-service');
    }

    public function generateConnectionId(): string
    {
        return uniqid('ws_', true);
    }

    public function generateHandshake(string $connectionId, string $token): array
    {
        return [
            'connection_id' => $connectionId,
            'timestamp' => time(),
            'token' => $token,
            'signature' => hash('sha256', $connectionId . $token . time())
        ];
    }

    public function validateToken(string $token): bool
    {
        try {
            // 检查 token 是否为空
            if (empty($token)) {
                $this->logger->warning('Empty token provided');
                return false;
            }

            // 检查 token 长度
            if (strlen($token) < 8) {
                $this->logger->warning('Token too short', ['token' => $token]);
                return false;
            }

            // 在实际生产环境中，你可能需要：
            // 1. 检查 token 格式（比如是否是有效的 JWT）
            // 2. 验证 token 是否过期
            // 3. 验证 token 签名
            // 4. 检查 token 是否在黑名单中
            // 5. 验证 token 权限等级

            // 这里为了测试，我们只验证测试 token
            if ($this->config->get('APP_ENV') === 'testing' && $token === 'test_token_123') {
                return true;
            }

            // 实际的 token 验证逻辑
            // TODO: 实现实际的 token 验证逻辑
            $isValid = $this->verifyTokenWithAuthService($token);

            if (!$isValid) {
                $this->logger->warning('Invalid token', ['token' => $token]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Token validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    private function verifyTokenWithAuthService(string $token): bool
    {
        // TODO: 实现与认证服务的集成
        // 这里应该调用你的认证服务来验证 token
        // 现在临时返回 true 用于测试
        return true;
    }

    public function validateHandshake(array $handshake): bool
    {
        // 检查必要字段
        if (!isset($handshake['connection_id'], $handshake['token'])) {
            return false;
        }

        // 如果没有提供时间戳和签名，则跳过签名验证
        if (!isset($handshake['timestamp'], $handshake['signature'])) {
            return true;
        }

        // 验证签名
        $expectedSignature = hash('sha256', $handshake['connection_id'] . $handshake['token'] . $handshake['timestamp']);
        return hash_equals($expectedSignature, $handshake['signature']);
    }

    public function prepareConnection(string $token, string $connectionId, ?string $clientId = null, array $extraParams = []): array
    {
        if (!$this->validateToken($token)) {
            throw new ValidationException('Invalid token');
        }

        return [
            'websocket_url' => $this->getWebSocketUrl($connectionId),
            'connection_id' => $connectionId,
            'client_id' => $clientId,
            'token' => $token,
            'extra_params' => $extraParams,
            'timestamp' => time()
        ];
    }

    public function getWebSocketUrl(string $connectionId): string
    {
        $wsHost = $this->config->get('WS_HOST', '127.0.0.1');
        $wsPort = $this->config->get('WS_PORT', '9502');
        $wsPath = $this->config->get('WS_PATH', 'ws');

        // 确保路径格式正确
        $path = trim($wsPath, '/');
        return "ws://{$wsHost}:{$wsPort}/{$path}/{$connectionId}";
    }
}
