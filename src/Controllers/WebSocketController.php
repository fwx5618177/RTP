<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Services\WebSocketService;
use Psr\Container\ContainerInterface;

class WebSocketController extends BaseController
{
    private WebSocketService $wsService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->wsService = new WebSocketService();
    }

    public function connect(Request $request): Response
    {
        $token = $request->getQueryParams()['token'] ?? null;
        $clientId = $request->getQueryParams()['client_id'] ?? null;

        if (! $token) {
            return $this->errorResponse('Token is required', 401);
        }

        // 生成唯一的连接ID
        $connectionId = $this->wsService->generateConnectionId();

        try {
            // 准备连接信息
            $connectionInfo = $this->wsService->prepareConnection(
                $token,
                $connectionId,
                $clientId,
                array_diff_key($request->getQueryParams(), array_flip(['token', 'client_id']))
            );

            // 生成握手信息
            $handshake = $this->wsService->generateHandshake($connectionId, $token);

            return $this->successResponse([
                'connection_id' => $connectionId,
                'websocket_url' => $connectionInfo['websocket_url'],
                'token' => $token,
                'handshake' => $handshake,
                'timestamp' => time(),
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function prepareConnection(Request $request): array
    {
        $token = $request->getQueryParams()['token'] ?? null;
        $clientId = $request->getQueryParams()['client_id'] ?? null;

        // 收集其他查询参数作为额外参数
        $extraParams = array_diff_key(
            $request->getQueryParams(),
            array_flip(['token', 'client_id'])
        );

        if (! $token) {
            throw new ValidationException('Token is required');
        }

        // 验证 token
        if (! $this->wsService->validateToken($token)) {
            throw new ValidationException('Invalid token');
        }

        return [
            'token' => $token,
            'client_id' => $clientId,
            'extra_params' => $extraParams,
            'connection_id' => uniqid('ws_', true),
            'timestamp' => time(),
        ];
    }
}
