<?php

namespace App\Server;

use App\Config\Config;
use App\Logs\Logger;
use App\Services\WebSocketService;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class WebSocketServer
{
    private Server $server;
    private string $host;
    private int $port;
    private Logger $logger;
    private WebSocketService $wsService;
    private array $connections = [];

    public function __construct()
    {
        $config = Config::getInstance();
        $this->host = $config->get('WS_HOST', '127.0.0.1');
        $this->port = (int)$config->get('WS_PORT', 9502);
        $this->logger = Logger::getInstance('websocket-server');
        $this->wsService = new WebSocketService();

        $this->server = new Server($this->host, $this->port);

        // 设置 Swoole 服务器配置
        $this->server->set([
            'heartbeat_check_interval' => 60,
            'heartbeat_idle_time' => 120,
            'worker_num' => 4,
            'max_request' => 1000,
            'log_level' => SWOOLE_LOG_INFO,
        ]);

        $this->registerEventHandlers();
    }

    private function registerEventHandlers(): void
    {
        $this->server->on('open', [$this, 'onOpen']);
        $this->server->on('message', [$this, 'onMessage']);
        $this->server->on('close', [$this, 'onClose']);
        $this->server->on('handshake', [$this, 'onHandshake']);
    }

    public function onHandshake(Request $request, $response): bool
    {
        $this->logger->info('WebSocket handshake request', [
            'headers' => $request->header,
            'path' => $request->server['request_uri'],
        ]);

        try {
            // 验证握手信息
            $handshakeData = [
                'connection_id' => $request->header['x-connection-id'] ?? '',
                'token' => $request->header['x-handshake-token'] ?? '',
                'timestamp' => $request->header['x-handshake-timestamp'] ?? '',
                'signature' => $request->header['x-handshake-signature'] ?? '',
            ];

            if (! $this->wsService->validateHandshake($handshakeData)) {
                $this->logger->warning('Invalid handshake', $handshakeData);

                return false;
            }

            // 设置 WebSocket 握手响应头
            $response->status(101);
            $response->header('Upgrade', 'websocket');
            $response->header('Connection', 'Upgrade');
            $response->header('Sec-WebSocket-Accept', base64_encode(sha1(
                $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
                true
            )));

            if (isset($request->header['sec-websocket-protocol'])) {
                $response->header('Sec-WebSocket-Protocol', $request->header['sec-websocket-protocol']);
            }

            // 准备连接
            if (! $this->prepareConnection($request)) {
                $this->logger->error('Failed to prepare connection');

                return false;
            }

            $this->logger->info('Handshake successful', [
                'connection_id' => $handshakeData['connection_id'],
                'fd' => $request->fd,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Handshake error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    public function onOpen(\Swoole\WebSocket\Server $server, Request $request): void
    {
        $this->logger->info('New WebSocket connection', [
            'fd' => $request->fd,
            'headers' => $request->header,
        ]);
    }

    public function onMessage(Server $server, Frame $frame): void
    {
        $this->logger->info('Received message', [
            'fd' => $frame->fd,
            'data' => $frame->data,
        ]);

        try {
            // 处理 ping 消息
            if (strtolower($frame->data) === 'ping') {
                $server->push($frame->fd, 'pong');

                return;
            }

            // 处理 JSON 消息
            $data = json_decode($frame->data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $response = [
                    'type' => $data['type'] ?? 'unknown',
                    'data' => $data['data'] ?? [],
                ];
                $server->push($frame->fd, json_encode($response));
            } else {
                // 如果不是 JSON，直接回显消息
                $server->push($frame->fd, $frame->data);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error processing message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $server->push($frame->fd, json_encode([
                'type' => 'error',
                'message' => 'Internal server error',
            ]));
        }
    }

    public function onClose(Server $server, int $fd): void
    {
        $this->logger->info('Connection closed', ['fd' => $fd]);
    }

    public function start(): void
    {
        $this->logger->info("Starting WebSocket server on ws://{$this->host}:{$this->port}");
        $this->server->start();
    }

    public function stop(): void
    {
        $this->server->stop();
    }

    private function prepareConnection(Request $request): bool
    {
        try {
            // 从 header 中获取 token
            $token = $request->header['x-handshake-token'] ?? null;

            if (! $token) {
                $this->logger->warning('WebSocket connection attempt without token');

                return false;
            }

            // 验证 token
            if (! $this->wsService->validateToken($token)) {
                $this->logger->warning('Invalid WebSocket token', ['token' => $token]);

                return false;
            }

            // 存储连接信息
            $connectionInfo = [
                'token' => $token,
                'connection_id' => $request->header['x-connection-id'] ?? null,
                'ip' => $request->server['remote_addr'],
                'user_agent' => $request->header['user-agent'] ?? 'unknown',
                'connected_at' => time(),
            ];

            // 存储连接信息
            $this->connections[$request->fd] = $connectionInfo;

            $this->logger->info('WebSocket connection prepared', [
                'fd' => $request->fd,
                'connection_id' => $connectionInfo['connection_id'],
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error preparing WebSocket connection', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
