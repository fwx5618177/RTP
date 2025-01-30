<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Gateway\JanusGateway;
use App\Logs\Logger;
use App\Http\JsonResponse;

class JanusController extends BaseController
{
    private JanusGateway $janusGateway;
    private Logger $logger;

    public function __construct()
    {
        $this->janusGateway = new JanusGateway();
        $this->logger = Logger::getInstance('janus-controller');
    }

    public function handleMessage(Request $request, string $sessionId, string $handleId): Response
    {
        try {
            $body = $request->getBodyParams();
            $response = $this->janusGateway->sendRequest("$sessionId/$handleId", $body);

            return new Response([
                'success' => true,
                'data' => $response,
                'code' => 200
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Message error", [
                'error' => $e->getMessage(),
                'sessionId' => $sessionId,
                'handleId' => $handleId,
                'body' => $body ?? null
            ]);

            return new Response([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    public function handleTrickle(Request $request, string $sessionId, string $handleId): Response
    {
        try {
            $body = $request->getBodyParams();

            // 确保请求体格式正确
            if (!isset($body['candidate'])) {
                throw new \Exception('Missing candidate in trickle request');
            }

            $response = $this->janusGateway->sendRequest("$sessionId/$handleId/trickle", [
                'janus' => 'trickle',
                'transaction' => $body['transaction'],
                'candidate' => $body['candidate']
            ]);

            // Janus trickle 请求可能返回空响应，这是正常的
            if (empty($response)) {
                return new Response([
                    'success' => true,
                    'code' => 200
                ]);
            }

            return new Response([
                'success' => true,
                'data' => $response,
                'code' => 200
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Trickle error", [
                'error' => $e->getMessage(),
                'sessionId' => $sessionId,
                'handleId' => $handleId,
                'body' => $body ?? null
            ]);

            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function joinRoom(Request $request)
    {
        try {
            $bodyParams = $request->getBodyParams();
            $roomId = (int)($bodyParams['roomId'] ?? 0);
            $display = $bodyParams['display'] ?? 'anonymous';

            if ($roomId <= 0) {
                throw new \InvalidArgumentException('Invalid room ID');
            }

            // 创建会话
            $session = $this->janusGateway->createSession();
            $sessionId = $session['data']['id'];

            // 附加到插件
            $plugin = $this->janusGateway->attachPlugin($sessionId);
            $handleId = $plugin['data']['id'];

            // 先尝试创建房间
            try {
                $this->janusGateway->createRoom($sessionId, $handleId, [
                    'roomId' => $roomId,
                    'description' => "Room $roomId",
                    'sampling_rate' => 16000,
                    'spatial_audio' => false,
                    'display' => $display
                ]);
            } catch (\App\Exceptions\GatewayException $e) {
                // 如果房间已存在则忽略错误
                if (!str_contains($e->getMessage(), 'already exists')) {
                    throw $e;
                }
                // 如果房间已存在，则直接加入
                $result = $this->janusGateway->joinRoom($sessionId, $handleId, $roomId, $display);
            }

            return $this->successResponse([
                'sessionId' => $sessionId,
                'handleId' => $handleId,
                'result' => $result ?? null
            ], 200);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
