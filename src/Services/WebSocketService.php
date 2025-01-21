<?php

namespace App\Services;

use App\Config\Config;
use App\Exceptions\ValidationException;
use App\Logs\Logger;
use App\Routes\WebSocketRouter;
use Swoole\WebSocket\Server;

class WebSocketService
{
    private Config $config;
    private Logger $logger;
    private array $connections = [];

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance('websocket-service');
    }

    public function generateConnectionId(): string
    {
        return uniqid('ws_', true);
    }

    public function generateHandshake(string $client_id, string $token): array
    {
        return [
            'client_id' => $client_id,
            'timestamp' => time(),
            'token' => $token,
            'signature' => hash('sha256', $client_id . $token . time()),
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

            if (! $isValid) {
                $this->logger->warning('Invalid token', ['token' => $token]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Token validation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
        if (! isset($handshake['connection_id'], $handshake['token'])) {
            return false;
        }

        // 如果没有提供时间戳和签名，则跳过签名验证
        if (! isset($handshake['timestamp'], $handshake['signature'])) {
            return true;
        }

        // 验证签名
        $expectedSignature = hash('sha256', $handshake['connection_id'] . $handshake['token'] . $handshake['timestamp']);

        return hash_equals($expectedSignature, $handshake['signature']);
    }

    public function prepareConnection(string $token, string $connectionId, ?string $clientId = null, array $extraParams = []): array
    {
        if (! $this->validateToken($token)) {
            throw new ValidationException('Invalid token');
        }

        return [
            'websocket_url' => $this->getWebSocketUrl($connectionId),
            'connection_id' => $connectionId,
            'client_id' => $clientId,
            'token' => $token,
            'extra_params' => $extraParams,
            'timestamp' => time(),
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

    /**
     * 处理普通消息
     */
    public function processMessage(array $message): array
    {
        try {
            // 验证消息格式
            if (!isset($message['content'])) {
                throw new ValidationException('Message content is required');
            }

            // 处理消息
            return [
                'type' => 'message',
                'status' => 'success',
                'content' => $message['content'],
                'timestamp' => time()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error processing message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'type' => 'message',
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 广播消息给所有连接的客户端
     */
    public function broadcast(Server $server, int $fromFd, array $message): void
    {
        try {
            if (!isset($message['message'])) {
                throw new ValidationException('Broadcast message is required');
            }

            $broadcastData = json_encode([
                'type' => 'broadcast',
                'from' => $fromFd,
                'message' => $message['message'],
                'timestamp' => time()
            ]);

            // 获取所有连接的客户端
            foreach ($server->connections as $fd) {
                // 不给发送者广播
                if ($fd !== $fromFd) {
                    $server->push($fd, $broadcastData);
                }
            }

            // 给发送者返回确认
            $server->push($fromFd, json_encode([
                'type' => 'broadcast',
                'status' => 'success',
                'message' => 'Broadcast sent successfully'
            ]));
        } catch (\Exception $e) {
            $this->logger->error('Error broadcasting message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'from_fd' => $fromFd
            ]);

            $server->push($fromFd, json_encode([
                'type' => 'broadcast',
                'status' => 'error',
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * 发送私聊消息
     */
    public function sendPrivateMessage(Server $server, int $fromFd, array $message): void
    {
        try {
            // 验证消息格式
            if (!isset($message['to']) || !isset($message['message'])) {
                throw new ValidationException('Invalid private message format');
            }

            // 在实际应用中，这里需要维护用户ID和连接FD的映射关系
            // 这里简化处理，假设 'to' 就是目标连接的 FD
            $toFd = (int)$message['to'];

            // 检查目标连接是否存在且有效
            if (!$server->exist($toFd)) {
                throw new ValidationException('Target user is not connected');
            }

            $privateData = json_encode([
                'type' => 'private',
                'from' => $fromFd,
                'message' => $message['message'],
                'timestamp' => time()
            ]);

            // 发送消息给目标用户
            $server->push($toFd, $privateData);

            // 给发送者返回确认
            $server->push($fromFd, json_encode([
                'type' => 'private',
                'status' => 'success',
                'to' => $toFd,
                'message' => 'Private message sent successfully'
            ]));
        } catch (\Exception $e) {
            $this->logger->error('Error sending private message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'from_fd' => $fromFd,
                'to' => $message['to'] ?? null
            ]);

            $server->push($fromFd, json_encode([
                'type' => 'private',
                'status' => 'error',
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * 存储连接信息
     */
    public function storeConnection(int $fd, array $connectionInfo): void
    {
        $this->connections[$fd] = $connectionInfo;
        $this->logger->info('Connection stored', [
            'fd' => $fd,
            'connection_id' => $connectionInfo['connection_id'] ?? null
        ]);
    }

    /**
     * 移除连接信息
     */
    public function removeConnection(int $fd): void
    {
        if (isset($this->connections[$fd])) {
            unset($this->connections[$fd]);
            $this->logger->info('Connection removed', ['fd' => $fd]);
        }
    }

    /**
     * 获取连接信息
     */
    public function getConnection(int $fd): ?array
    {
        return $this->connections[$fd] ?? null;
    }

    /**
     * 处理 WebSocket 握手
     */
    public function handleHandshake(Server $server, int $fd, array $handshakeData): bool
    {
        try {
            if (!$this->validateHandshake($handshakeData)) {
                throw new ValidationException('Invalid handshake');
            }

            if (!$this->validateToken($handshakeData['token'])) {
                throw new ValidationException('Invalid token');
            }

            $this->storeConnection($fd, [
                'connection_id' => $handshakeData['connection_id'],
                'token' => $handshakeData['token'],
                'timestamp' => time()
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Handshake failed', [
                'error' => $e->getMessage(),
                'fd' => $fd
            ]);
            return false;
        }
    }

    /**
     * 处理 WebSocket 消息
     */
    public function handleMessage(Server $server, int $fd, string $data): void
    {
        try {
            $message = json_decode($data, true);
            if (!$message) {
                throw new ValidationException('Invalid message format');
            }

            // 处理消息
            $response = $this->processMessage($message);

            // 发送响应
            $server->push($fd, json_encode($response));
        } catch (\Exception $e) {
            $this->logger->error('Error handling message', [
                'error' => $e->getMessage(),
                'fd' => $fd
            ]);

            $server->push($fd, json_encode([
                'status' => 'error',
                'error' => $e->getMessage()
            ]));
        }
    }

    /**
     * 处理连接关闭
     */
    public function handleClose(Server $server, int $fd): void
    {
        $this->removeConnection($fd);
        $this->logger->info('Connection closed', ['fd' => $fd]);
    }
}
