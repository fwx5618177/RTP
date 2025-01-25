<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTO\RoomDTO;
use App\Exceptions\RoomException;
use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;
use App\Services\RoomService;
use Psr\Container\ContainerInterface;

class RoomController extends BaseController
{
    private RoomService $roomService;
    private Logger $logger;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->roomService = $container->get(RoomService::class);
        $this->logger = Logger::getInstance('room-controller');
    }

    public function createRoom(Request $request): Response
    {
        $data = $request->getBodyParams();
        $this->logger->info('Creating room with data', ['data' => $data]);

        // 基本参数验证
        if (empty($data['userId'])) {
            $this->logger->warning('userId is required but was empty');
            return (new Response())
                ->setStatusCode(400)
                ->setBody(['error' => 'userId is required']);
        }

        if (empty($data['roomName'])) {
            $this->logger->warning('roomName is required but was empty');
            return (new Response())
                ->setStatusCode(400)
                ->setBody(['error' => 'roomName is required']);
        }

        // 配置验证
        if (isset($data['config'])) {
            $configErrors = $this->validateRoomConfig($data['config']);
            if (!empty($configErrors)) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'Invalid configuration', 'details' => $configErrors]);
            }
        }

        $roomDTO = new RoomDTO($data['userId'], $data['roomName'], $data['config'] ?? []);
        $this->logger->debug('Created RoomDTO', ['dto' => [
            'userId' => $roomDTO->getUserId(),
            'roomName' => $roomDTO->getRoomName(),
            'config' => $roomDTO->getConfig(),
        ]]);

        try {
            $room = $this->roomService->createRoom($roomDTO);
            $this->logger->info('Room created successfully', [
                'roomId' => $room->getRoomId(),
                'createdAt' => $room->getCreatedAt()->format('c'),
            ]);

            // 获取房间配置中的 Janus 信息
            $config = $room->getConfig();
            $janusInfo = $config['janus'] ?? [];

            return (new Response())
                ->setStatusCode(201)
                ->setBody([
                    'roomId' => $room->getRoomId(),
                    'createdAt' => $room->getCreatedAt()->format('c'),
                    'config' => $room->getConfig(),
                    'userId' => $roomDTO->getUserId(),
                    'roomName' => $roomDTO->getRoomName(),
                    'mediaSession' => [
                        'janusRoomId' => $janusInfo['roomId'] ?? null,
                        'sessionId' => $janusInfo['sessionId'] ?? null,
                        'handleId' => $janusInfo['handleId'] ?? null
                    ]
                ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create room', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function joinRoom(Request $request): Response
    {
        try {
            $data = $request->getBodyParams();

            // Validate required parameters
            if (empty($data['roomId'])) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'roomId is required']);
            }

            if (empty($data['userId'])) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'userId is required']);
            }

            $roomId = $data['roomId'];
            $userId = $data['userId'];

            // Check for SIP headers
            if ($request->getHeader('X-Conference-Room') && $request->getHeader('X-Conference-Server')) {
                // Handle SIP call routing
                return $this->handleSipCall($request, $roomId, $userId);
            }

            $result = $this->roomService->joinRoom($roomId, $userId);

            return (new Response())
                ->setStatusCode(200)
                ->setBody($result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to join room', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $statusCode = 500;
            if ($e instanceof RoomException) {
                $statusCode = 404;
            }

            return (new Response())
                ->setStatusCode($statusCode)
                ->setBody(['error' => $e->getMessage()]);
        }
    }

    public function leaveRoom(Request $request): Response
    {
        try {
            $data = $request->getBodyParams();

            // Validate required parameters
            if (empty($data['roomId'])) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'roomId is required']);
            }

            if (empty($data['userId'])) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'userId is required']);
            }

            $roomId = $data['roomId'];
            $userId = $data['userId'];

            $this->roomService->leaveRoom($roomId, $userId);

            return (new Response())->setStatusCode(204);
        } catch (\Exception $e) {
            $this->logger->error('Failed to leave room', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $statusCode = 500;
            if ($e instanceof RoomException) {
                $statusCode = 404;
            }

            return (new Response())
                ->setStatusCode($statusCode)
                ->setBody(['error' => $e->getMessage()]);
        }
    }

    private function handleSipCall(Request $request, string $roomId, string $userId): Response
    {
        try {
            // 获取 SIP 头信息
            $sipHeaders = [
                'X-Conference-Room' => $request->getHeader('X-Conference-Room'),
                'X-Conference-Server' => $request->getHeader('X-Conference-Server')
            ];

            // 验证必要的 SIP 头
            if (empty($sipHeaders['X-Conference-Room']) || empty($sipHeaders['X-Conference-Server'])) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'Missing required SIP headers']);
            }

            // 获取房间信息
            $room = $this->roomService->findRoom($roomId);
            if (!$room) {
                return (new Response())
                    ->setStatusCode(404)
                    ->setBody(['error' => 'Room not found']);
            }

            // 加入房间（使用特殊的 SIP 用户标识）
            $sipUserId = "sip:{$userId}@{$sipHeaders['X-Conference-Server']}";
            $joinResult = $this->roomService->joinRoom($roomId, $sipUserId);

            // 记录 SIP 呼叫信息
            $this->logger->info('SIP call routed', [
                'roomId' => $roomId,
                'sipUserId' => $sipUserId,
                'headers' => $sipHeaders
            ]);

            return (new Response())
                ->setStatusCode(200)
                ->setBody([
                    'message' => 'SIP call routed successfully',
                    'roomId' => $roomId,
                    'sipUserId' => $sipUserId,
                    'joinResult' => $joinResult
                ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle SIP call', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'roomId' => $roomId,
                'userId' => $userId
            ]);

            return (new Response())
                ->setStatusCode(500)
                ->setBody(['error' => $e->getMessage()]);
        }
    }

    private function validateRoomConfig(array $config): array
    {
        $errors = [];

        // 验证最大参与者数
        if (isset($config['maxParticipants'])) {
            if (!is_int($config['maxParticipants']) || $config['maxParticipants'] <= 0) {
                $errors[] = 'maxParticipants must be a positive integer';
            }
            if ($config['maxParticipants'] > 100) { // 设置上限
                $errors[] = 'maxParticipants cannot exceed 100';
            }
        }

        // 验证音频配置
        if (isset($config['audioEnabled'])) {
            if (!is_bool($config['audioEnabled'])) {
                $errors[] = 'audioEnabled must be a boolean value';
            }
        }

        // 验证视频配置
        if (isset($config['videoEnabled'])) {
            if (!is_bool($config['videoEnabled'])) {
                $errors[] = 'videoEnabled must be a boolean value';
            }
        }

        // 验证音频配置详情
        if (isset($config['audioConfig'])) {
            if (!is_array($config['audioConfig'])) {
                $errors[] = 'audioConfig must be an object';
            } else {
                if (isset($config['audioConfig']['sampleRate'])) {
                    $validSampleRates = [8000, 16000, 32000, 44100, 48000];
                    if (!in_array($config['audioConfig']['sampleRate'], $validSampleRates)) {
                        $errors[] = 'Invalid sample rate. Must be one of: ' . implode(', ', $validSampleRates);
                    }
                }
                if (isset($config['audioConfig']['channels'])) {
                    if (!in_array($config['audioConfig']['channels'], [1, 2])) {
                        $errors[] = 'Channels must be either 1 (mono) or 2 (stereo)';
                    }
                }
            }
        }

        return $errors;
    }
}
