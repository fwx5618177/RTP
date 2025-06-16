<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Http\Response;
use Swoole\Http\Request as SwooleRequest;
use App\Services\WebSocketService;
use Psr\Container\ContainerInterface;
use Swoole\WebSocket\Server;
use App\Logs\Logger;
use Swoole\WebSocket\Frame;
use App\Http\Request;

class WebSocketController extends BaseController
{
    private WebSocketService $wsService;
    private Logger $logger;
    private array $connections = [];

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->wsService = $container->get(WebSocketService::class);
        $this->logger = Logger::getInstance('websocket-controller');
    }

    public function connect(Request $request): Response
    {
        try {
            // 验证 token
            $token = $request->getQueryParams()['token'] ?? null;
            if (!$token) {
                return $this->errorResponse('Missing token parameter', 401);
            }

            // 这里可以添加更多的 token 验证逻辑
            if ($token !== 'test_token_123') {  // 示例验证，实际应该使用更安全的验证方式
                return $this->errorResponse('Invalid token', 401);
            }

            // 返回成功响应
            return $this->successResponse([
                'message' => 'WebSocket connection authorized',
                'token' => $token,
                'websocket_url' => 'ws://localhost:9501',  // 返回 WebSocket 服务器地址
                'connection_id' => '1234567890',  // 返回连接 ID
                'client_id' => 'client_123',  // 返回客户端 ID
            ]);
        } catch (\Exception $e) {
            $this->logger->error('WebSocket connection error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Internal server error', 500);
        }
    }

    public function prepareConnection(SwooleRequest $request): bool
    {
        try {
            // 从请求中提取参数
            $clientId = $request->get['client_id'] ?? null;
            $token = $request->header['x-handshake-token'] ?? null;
            $connectionId = $request->header['x-connection-id'] ?? null;

            if (!$token) {
                $this->logger->warning('WebSocket connection attempt without token');
                return false;
            }

            // 收集额外参数
            $extraParams = array_diff_key(
                $request->get ?? [],
                array_flip(['client_id'])
            );

            // 准备连接信息
            $connectionInfo = [
                'token' => $token,
                'connection_id' => $connectionId,
                'client_id' => $clientId,
                'ip' => $request->server['remote_addr'],
                'user_agent' => $request->header['user-agent'] ?? 'unknown',
                'extra_params' => $extraParams,
                'connected_at' => time(),
            ];

            // 存储连接信息
            $this->wsService->storeConnection($request->fd, $connectionInfo);

            $this->logger->info('WebSocket connection prepared', [
                'fd' => $request->fd,
                'connection_id' => $connectionId
            ]);

            return true;
        } catch (ValidationException $e) {
            $this->logger->error('Connection preparation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 处理 WebSocket 握手
     */
    public function handleHandshake(Server $server, SwooleRequest $request, Response $response): bool
    {
        try {
            // 准备连接
            if (!$this->prepareConnection($request)) {
                return false;
            }

            // 从请求头中获取握手数据
            $handshakeData = [
                'connection_id' => $request->header['x-connection-id'] ?? '',
                'token' => $request->header['x-handshake-token'] ?? '',
                'timestamp' => $request->header['x-handshake-timestamp'] ?? '',
                'signature' => $request->header['x-handshake-signature'] ?? ''
            ];

            return $this->wsService->handleHandshake($server, $request->fd, $handshakeData);
        } catch (\Exception $e) {
            $this->logger->error('Handshake failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * 处理消息
     */
    public function handleMessage(Server $server, Frame $frame): void
    {
        try {
            $this->wsService->handleMessage($server, $frame->fd, $frame->data);
        } catch (\Exception $e) {
            $this->logger->error('Message handling failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $server->push($frame->fd, json_encode([
                'status' => 'error',
                'error' => $e->getMessage()
            ]));
        }
    }

    public function handlePing(Server $server, Frame $frame): void
    {
        $server->push($frame->fd, json_encode(['type' => 'pong']));
    }

    public function handleBroadcast(Server $server, Frame $frame): void
    {
        $data = json_decode($frame->data, true);
        $this->wsService->broadcast($server, $frame->fd, $data);
    }

    public function handlePrivateMessage(Server $server, Frame $frame): void
    {
        $data = json_decode($frame->data, true);
        $this->wsService->sendPrivateMessage($server, $frame->fd, $data);
    }

    public function handleClose(Server $server, int $fd): void
    {
        $this->wsService->handleClose($server, $fd);
    }
}
