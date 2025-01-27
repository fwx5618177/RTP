<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Config;
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
    private string $janusWsUrl;
    private Config $config;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->roomService = $container->get(RoomService::class);
        $this->logger = Logger::getInstance('room-controller');
        $this->config = Config::getInstance();
        $this->janusWsUrl = $this->config->get('JANUS_WS_ENDPOINT', 'ws://127.0.0.1:8188');
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
            if (! empty($configErrors)) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'Invalid configuration', 'details' => $configErrors]);
            }
        }

        try {
            $roomDTO = new RoomDTO($data['userId'], $data['roomName'], $data['config'] ?? []);
            $room = $this->roomService->createRoom($roomDTO);

            // 构建扁平化的返回数据结构
            $responseData = [
                'roomId' => $room->getRoomId(),
                'name' => $room->getRoomName(),
                'createdAt' => $room->getCreatedAt()->format('c'),
                'creator' => $data['userId'],
                'maxParticipants' => $data['config']['maxParticipants'] ?? 10,
                'audioEnabled' => $data['config']['audioEnabled'] ?? true,
                'videoEnabled' => $data['config']['videoEnabled'] ?? false,
                'janus' => [
                    'sessionId' => $room->getJanusSessionId(),
                    'handleId' => $room->getJanusHandleId(),
                    'wsUrl' => $this->janusWsUrl,
                ],
            ];

            // 如果存在音频配置，添加到响应中
            if (isset($data['config']['audioConfig'])) {
                $responseData['sampleRate'] = $data['config']['audioConfig']['sampleRate'] ?? 16000;
                $responseData['channels'] = $data['config']['audioConfig']['channels'] ?? 1;
                $responseData['codec'] = $data['config']['audioConfig']['codec'] ?? 'opus';
            }

            $this->logger->info('Room created successfully', [
                'roomId' => $room->getRoomId(),
                'createdAt' => $room->getCreatedAt()->format('c'),
            ]);

            return $this->successResponse($responseData, 201);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create room', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (new Response())
                ->setStatusCode(500)
                ->setBody(['error' => $e->getMessage()]);
        }
    }

    public function joinRoom(Request $request): Response
    {
        try {
            $data = $request->getBodyParams();

            // 添加请求数据日志
            $this->logger->info('Join room request data', [
                'method' => $request->getMethod(),
                'headers' => $request->getHeaders(),
                'body' => $data,
                'raw' => $request->getBodyParams()
            ]);

            // Validate required parameters
            if (empty($data['roomId'])) {
                $this->logger->warning('roomId is required but was empty', ['data' => $data]);
                return $this->errorResponse('roomId is required', 400);
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

            // 新增：获取房间信息，以获取 sessionId 和 handleId
            $room = $this->roomService->findRoom($roomId);
            if (!$room) {
                return $this->errorResponse('Room not found', 404);
            }

            // 使用房间的 sessionId 和 handleId
            $result = $this->roomService->joinRoom($roomId, $userId);

            // 使用房间实体中的 sessionId 和 handleId
            $this->logger->info('User joined room', [
                'roomId' => $roomId,
                'userId' => $userId,
                'sessionId' => $room->getJanusSessionId(),  // 使用房间实体的 sessionId
                'handleId' => $room->getJanusHandleId(),    // 使用房间实体的 handleId
                "result" => $result
            ]);

            return $this->successResponse([
                'roomId' => $roomId,
                'userId' => $userId,
                'janus' => [
                    'sessionId' => $room->getJanusSessionId(),  // 使用房间实体的 sessionId
                    'handleId' => $room->getJanusHandleId(),    // 使用房间实体的 handleId
                    'wsUrl' => $this->janusWsUrl,
                ],
            ], 200);
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
                'X-Conference-Server' => $request->getHeader('X-Conference-Server'),
            ];

            // 验证必要的 SIP 头
            if (empty($sipHeaders['X-Conference-Room']) || empty($sipHeaders['X-Conference-Server'])) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'Missing required SIP headers']);
            }

            // 获取房间信息
            $room = $this->roomService->findRoom($roomId);
            if (! $room) {
                $this->logger->warning('Room not found, room id not found', ['roomId' => $roomId]);

                return $this->errorResponse('Room not found, room id not found', 404);
            }

            // 加入房间（使用特殊的 SIP 用户标识）
            $sipUserId = "sip:{$userId}@{$sipHeaders['X-Conference-Server']}";
            $joinResult = $this->roomService->joinRoom($roomId, $sipUserId);

            // 记录 SIP 呼叫信息
            $this->logger->info('SIP call routed', [
                'roomId' => $roomId,
                'sipUserId' => $sipUserId,
                'headers' => $sipHeaders,
            ]);

            return (new Response())
                ->setStatusCode(200)
                ->setBody([
                    'message' => 'SIP call routed successfully',
                    'roomId' => $roomId,
                    'sipUserId' => $sipUserId,
                    'joinResult' => $joinResult,
                ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle SIP call', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'roomId' => $roomId,
                'userId' => $userId,
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
            if (! is_int($config['maxParticipants']) || $config['maxParticipants'] <= 0) {
                $errors[] = 'maxParticipants must be a positive integer';
            }
            if ($config['maxParticipants'] > 100) { // 设置上限
                $errors[] = 'maxParticipants cannot exceed 100';
            }
        }

        // 验证音频配置
        if (isset($config['audioEnabled'])) {
            if (! is_bool($config['audioEnabled'])) {
                $errors[] = 'audioEnabled must be a boolean value';
            }
        }

        // 验证视频配置
        if (isset($config['videoEnabled'])) {
            if (! is_bool($config['videoEnabled'])) {
                $errors[] = 'videoEnabled must be a boolean value';
            }
        }

        // 验证音频配置详情
        if (isset($config['audioConfig'])) {
            if (! is_array($config['audioConfig'])) {
                $errors[] = 'audioConfig must be an object';
            } else {
                if (isset($config['audioConfig']['sampleRate'])) {
                    $validSampleRates = [8000, 16000, 32000, 44100, 48000];
                    if (! in_array($config['audioConfig']['sampleRate'], $validSampleRates)) {
                        $errors[] = 'Invalid sample rate. Must be one of: ' . implode(', ', $validSampleRates);
                    }
                }
                if (isset($config['audioConfig']['channels'])) {
                    if (! in_array($config['audioConfig']['channels'], [1, 2])) {
                        $errors[] = 'Channels must be either 1 (mono) or 2 (stereo)';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * 获取房间详情
     */
    public function getRoomDetails(Request $request, string $roomId): Response
    {
        try {
            $this->logger->info('find roomId:' . $roomId);
            $room = $this->roomService->findRoom($roomId);

            if (! $room) {
                $this->logger->warning('Room detail not found, room id not found', ['roomId' => $roomId]);

                return $this->errorResponse('Room detail not found, room id not found', 404);
            }

            // 构建扁平化的返回数据
            $responseData = [
                'roomId' => $room->getRoomId(),
                'name' => $room->getRoomName(),
                'createdAt' => $room->getCreatedAt()->format('c'),
                'creator' => $room->getCreatorId(),
                'maxParticipants' => $room->getMaxParticipants() ?? 10,
                'janusSessionId' => $room->getJanusSessionId(),
                'janusHandleId' => $room->getJanusHandleId(),
                'participantsCount' => $this->roomService->getParticipantsCount($roomId),
            ];

            // 如果存在音频配置，添加到响应中
            if (isset($room->getConfig()['audioConfig'])) {
                $audioConfig = $room->getConfig()['audioConfig'];
                $responseData['sampleRate'] = $audioConfig['sampleRate'] ?? 16000;
                $responseData['channels'] = $audioConfig['channels'] ?? 1;
                $responseData['codec'] = $audioConfig['codec'] ?? 'opus';
            }

            return $this->successResponse($responseData, 200);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get room details', [
                'error' => $e->getMessage(),
                'roomId' => $roomId,
            ]);

            return $this->errorResponse('Failed to get room details', 500);
        }
    }

    /**
     * 获取房间参与者列表
     */
    public function getRoomParticipants(Request $request, string $roomId): Response
    {
        try {
            $room = $this->roomService->findRoom($roomId);

            if (! $room) {
                $this->logger->warning('Room participants not found, room id not found', ['roomId' => $roomId]);

                return $this->errorResponse('Room participants not found, room id not found', 404);
            }

            $participants = $this->roomService->getRoomParticipants($roomId);

            return $this->successResponse([
                'roomId' => $roomId,
                'count' => count($participants),
                'participants' => array_map(function ($participant) {
                    return [
                        'userId' => $participant['userId'],
                        'display' => $participant['display'],
                        'joinedAt' => $participant['joinedAt'],
                        'audioMuted' => $participant['audioMuted'] ?? false,
                        'isActive' => $participant['isActive'] ?? true,
                    ];
                }, $participants),
            ], 200);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get room participants', [
                'error' => $e->getMessage(),
                'roomId' => $roomId,
            ]);

            return $this->errorResponse('Failed to get room participants', 500);
        }
    }
}
