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

    public function __construct(JanusGateway $janusGateway)
    {
        $this->janusGateway = $janusGateway;
        $this->logger = Logger::getInstance('janus-controller');
    }

    /**
     * 创建 Janus 会话
     */
    public function createSession(Request $request): Response
    {
        try {
            $this->logger->info('Creating Janus session');

            // 创建 Janus 会话
            $session = $this->janusGateway->createSession();
            $sessionId = $session['data']['id'];

            // 附加到 AudioBridge 插件
            $audioBridgeHandle = $this->janusGateway->attachPlugin($sessionId);
            $audioBridgeHandleId = $audioBridgeHandle['data']['id'];

            // 附加到 SIP 插件
            $sipHandle = $this->janusGateway->attachPlugin($sessionId, JanusGateway::PLUGIN_SIP);
            $sipHandleId = $sipHandle['data']['id'];

            return $this->successResponse([
                'sessionId' => $sessionId,
                'audioBridgeHandleId' => $audioBridgeHandleId,
                'sipHandleId' => $sipHandleId
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create Janus session', [
                'error' => $e->getMessage()
            ]);
            return $this->errorResponse('Failed to create Janus session: ' . $e->getMessage());
        }
    }

    /**
     * 创建 SIP 桥接
     */
    public function createSipBridge(Request $request, string $sessionId): Response
    {
        $data = $request->getBodyParams();
        $roomId = $data['roomId'] ?? null;
        $uri = $data['uri'] ?? null;
        $muted = $data['muted'] ?? false;
        $quality = $data['quality'] ?? 4;

        if (!$roomId || !$uri) {
            return $this->errorResponse('Room ID and URI are required');
        }

        try {
            $this->logger->info('Creating SIP bridge', [
                'sessionId' => $sessionId,
                'roomId' => $roomId,
                'uri' => $uri
            ]);

            // 获取 SIP 插件句柄
            $handles = $this->janusGateway->listHandles($sessionId);
            $sipHandleId = null;
            foreach ($handles['data']['handles'] as $handleId) {
                $info = $this->janusGateway->handleInfo($sessionId, $handleId);
                if ($info['data']['plugin'] === JanusGateway::PLUGIN_SIP) {
                    $sipHandleId = $handleId;
                    break;
                }
            }

            if (!$sipHandleId) {
                throw new \Exception('SIP plugin handle not found');
            }

            // 创建 SIP 桥接
            $result = $this->janusGateway->createSipBridgeSession($sessionId, $sipHandleId, [
                'roomId' => $roomId,
                'uri' => $uri,
                'muted' => $muted,
                'quality' => $quality
            ]);

            return $this->successResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create SIP bridge', [
                'error' => $e->getMessage(),
                'sessionId' => $sessionId
            ]);
            return $this->errorResponse('Failed to create SIP bridge: ' . $e->getMessage());
        }
    }

    /**
     * 更新 SIP 桥接
     */
    public function updateSipBridge(Request $request, string $sessionId): Response
    {
        $data = $request->getBodyParams();
        $muted = $data['muted'] ?? null;
        $quality = $data['quality'] ?? null;

        if ($muted === null) {
            return $this->errorResponse('Muted status is required');
        }

        try {
            $this->logger->info('Updating SIP bridge', [
                'sessionId' => $sessionId,
                'muted' => $muted,
                'quality' => $quality
            ]);

            // 获取 SIP 句柄
            $handles = $this->janusGateway->listHandles($sessionId);
            $sipHandleId = null;
            foreach ($handles['data']['handles'] as $handleId) {
                $info = $this->janusGateway->handleInfo($sessionId, $handleId);
                if ($info['data']['plugin'] === JanusGateway::PLUGIN_SIP) {
                    $sipHandleId = $handleId;
                    break;
                }
            }

            if (!$sipHandleId) {
                throw new \Exception('SIP plugin handle not found');
            }

            // 更新 SIP 桥接
            $result = $this->janusGateway->updateSipBridge($sessionId, $sipHandleId, [
                'muted' => $muted,
                'quality' => $quality
            ]);

            return $this->successResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update SIP bridge', [
                'error' => $e->getMessage(),
                'sessionId' => $sessionId
            ]);
            return $this->errorResponse('Failed to update SIP bridge: ' . $e->getMessage());
        }
    }

    /**
     * 断开 SIP 桥接
     */
    public function disconnectSipBridge(Request $request, string $sessionId): Response
    {
        try {
            $this->logger->info('Disconnecting SIP bridge', [
                'sessionId' => $sessionId
            ]);

            // 获取 SIP 句柄
            $handles = $this->janusGateway->listHandles($sessionId);
            $sipHandleId = null;
            foreach ($handles['data']['handles'] as $handleId) {
                $info = $this->janusGateway->handleInfo($sessionId, $handleId);
                if ($info['data']['plugin'] === JanusGateway::PLUGIN_SIP) {
                    $sipHandleId = $handleId;
                    break;
                }
            }

            if (!$sipHandleId) {
                throw new \Exception('SIP plugin handle not found');
            }

            // 断开 SIP 桥接
            $result = $this->janusGateway->disconnectSipBridge($sessionId, $sipHandleId);

            return $this->successResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to disconnect SIP bridge', [
                'error' => $e->getMessage(),
                'sessionId' => $sessionId
            ]);
            return $this->errorResponse('Failed to disconnect SIP bridge: ' . $e->getMessage());
        }
    }

    /**
     * 销毁 Janus 会话
     */
    public function destroySession(Request $request, string $sessionId): Response
    {
        try {
            $this->logger->info('Destroying Janus session', [
                'sessionId' => $sessionId
            ]);

            $result = $this->janusGateway->destroySession($sessionId);
            return $this->successResponse($result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to destroy Janus session', [
                'error' => $e->getMessage(),
                'sessionId' => $sessionId
            ]);
            return $this->errorResponse('Failed to destroy Janus session: ' . $e->getMessage());
        }
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

    public function joinRoom(Request $request): Response
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
                $this->janusGateway->createAudioRoom($sessionId, $handleId, [
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
            }

            // 加入房间
            $result = $this->janusGateway->joinAudioRoom($sessionId, $handleId, $roomId, $display);

            return $this->successResponse([
                'sessionId' => $sessionId,
                'handleId' => $handleId,
                'result' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to join room', [
                'error' => $e->getMessage(),
                'roomId' => $roomId ?? null,
                'display' => $display ?? null
            ]);
            return $this->errorResponse($e->getMessage());
        }
    }
}
