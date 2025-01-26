<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\RoomDTO;
use App\Entity\RoomEntity;
use App\Exceptions\RoomException;
use App\Logs\Logger;
use App\Repository\RoomRepository;
use App\Utils\Container;
use App\Validator\Validator;
use App\Media\MediaManager;

class RoomService extends BaseService
{
    private Logger $logger;
    private MediaManager $mediaManager;

    public function __construct(
        protected RoomRepository $roomRepository,
        protected Validator $validator,
        protected RedisService $redisService
    ) {
        parent::__construct();
        $this->logger = Container::getInstance()->get(Logger::class);
        $this->mediaManager = new MediaManager();
    }

    public function createRoom(RoomDTO $roomDTO): RoomEntity
    {
        try {
            $this->logger->info('Starting room creation', [
                'roomName' => $roomDTO->getRoomName()
            ]);

            // 验证输入
            $data = [
                'roomName' => $roomDTO->getRoomName(),
                'config' => $roomDTO->getConfig(),
            ];

            $rules = [
                'roomName' => 'required|string|min:3|max:50',
                'config' => 'required|array',
            ];

            $this->validator->validate($data, $rules);

            // 创建音频房间
            $mediaInfo = $this->mediaManager->createAudioRoom(
                $roomDTO->getRoomName(),
                $roomDTO->getUserId()
            );

            // 创建房间实体
            $roomEntity = new RoomEntity(
                (string)$mediaInfo['roomId'],
                $roomDTO->getRoomName(),
                $roomDTO->getUserId(),
                $mediaInfo['sessionId'],
                $mediaInfo['handleId'],
                $roomDTO->getConfig()['maxParticipants'] ?? 10
            );

            $this->logger->info('Saving room to database', [
                'roomId' => $roomEntity->getRoomId(),
                'roomName' => $roomEntity->getRoomName()
            ]);

            // 保存到数据库
            $savedRoom = $this->roomRepository->save($roomEntity);

            // 保存扩展配置到 Redis
            $roomKey = "room:{$mediaInfo['roomId']}:metadata";
            $this->logger->info('Saving room metadata to Redis', [
                'roomKey' => $roomKey
            ]);

            // 保存到 Redis
            $this->redisService->hSet($roomKey, [
                'name' => $savedRoom->getRoomName(),
                'creator' => $roomDTO->getUserId(),
                'created_at' => $savedRoom->getCreatedAt()->format('Y-m-d H:i:s'),
                'status' => 'active',
                'audio_enabled' => $roomDTO->getConfig()['audioEnabled'] ?? true,
                'video_enabled' => $roomDTO->getConfig()['videoEnabled'] ?? false,
                'sample_rate' => $roomDTO->getConfig()['audioConfig']['sampleRate'] ?? 16000,
                'channels' => $roomDTO->getConfig()['audioConfig']['channels'] ?? 1,
                'codec' => $roomDTO->getConfig()['audioConfig']['codec'] ?? 'opus'
            ]);

            return $savedRoom;
        } catch (\Exception $e) {
            $this->logger->error('Error in createRoom', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function joinRoom(string $roomId, string $userId): array
    {
        try {
            if (empty($roomId) || empty($userId)) {
                throw new RoomException('Room ID and User ID are required');
            }

            // 检查房间是否存在
            $metadata = $this->redisService->hGetAll("room:$roomId:metadata");
            if (empty($metadata)) {
                throw new RoomException('Room not found');
            }

            // 检查用户是否已在房间中
            if ($this->redisService->sIsMember("room:$roomId:participants", $userId)) {
                return [
                    'roomId' => $roomId,
                    'metadata' => $metadata,
                    'participants' => $this->redisService->sMembers("room:$roomId:participants")
                ];
            }
            // 加入房间
            $this->redisService->sAdd("room:$roomId:participants", [$userId]);

            $this->logger->info('User joined room', [
                'roomId' => $roomId,
                'userId' => $userId
            ]);

            return [
                'roomId' => $roomId,
                'metadata' => $metadata,
                'participants' => $this->redisService->sMembers("room:$roomId:participants")
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error in joinRoom', [
                'error' => $e->getMessage(),
                'roomId' => $roomId,
                'userId' => $userId
            ]);
            throw $e;
        }
    }

    public function leaveRoom(string $roomId, string $userId): void
    {
        try {
            if (empty($roomId) || empty($userId)) {
                throw new RoomException('Room ID and User ID are required');
            }

            // 检查房间是否存在
            if (!$this->redisService->exists("room:$roomId:metadata")) {
                throw new RoomException('Room not found');
            }

            // 从参与者列表中移除
            $this->redisService->sRem("room:$roomId:participants", $userId);

            $this->logger->info('User left room', [
                'roomId' => $roomId,
                'userId' => $userId
            ]);

            // 检查房间是否为空
            $participantCount = $this->redisService->sCard("room:$roomId:participants");
            if ($participantCount === 0) {
                $this->cleanupRoom($roomId);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error in leaveRoom', [
                'error' => $e->getMessage(),
                'roomId' => $roomId,
                'userId' => $userId
            ]);
            throw $e;
        }
    }

    private function cleanupRoom(string $roomId): void
    {
        // 删除 Redis 数据
        $this->redisService->del([
            "room:$roomId:metadata",
            "room:$roomId:participants"
        ]);
        // 从数据库中删除
        $room = $this->roomRepository->find($roomId);
        if ($room) {
            $this->roomRepository->delete($room);
        }
    }

    public function findRoom(string $roomId): ?RoomEntity
    {
        try {
            // 先从 Redis 获取
            $metadata = $this->redisService->hGetAll("room:$roomId:metadata");
            $this->logger->info('Room id found', [
                'roomId' => $roomId,
                'metadata' => $metadata,
            ]);
            if (empty($metadata)) {
                $this->logger->warning('Room not found in Redis', ['roomId' => $roomId]);
                return null;
            }

            // 从数据库获取完整信息
            $room = $this->roomRepository->findByRoomId($roomId);
            if (!$room) {
                $this->logger->warning('Room not found in database', ['roomId' => $roomId]);
                // 如果数据库中没有，但Redis中有，我们应该清理Redis数据
                $this->redisService->del(["room:$roomId:metadata", "room:$roomId:participants"]);
                return null;
            }

            return $room;
        } catch (\Exception $e) {
            $this->logger->error('Error in findRoom', [
                'error' => $e->getMessage(),
                'roomId' => $roomId
            ]);
            throw $e;
        }
    }

    /**
     * 获取房间参与者数量
     */
    public function getParticipantsCount(string $roomId): int
    {
        return (int)$this->redisService->sCard("room:$roomId:participants");
    }

    /**
     * 获取房间参与者列表
     */
    public function getRoomParticipants(string $roomId): array
    {
        try {
            $participantIds = $this->redisService->sMembers("room:$roomId:participants");
            $participants = [];

            foreach ($participantIds as $userId) {
                $participantData = $this->redisService->hGetAll("room:$roomId:participant:$userId");
                if (!empty($participantData)) {
                    $participants[] = [
                        'userId' => $userId,
                        'display' => $participantData['display'] ?? $userId,
                        'joinedAt' => $participantData['joinedAt'] ?? null,
                        'audioMuted' => (bool)($participantData['audioMuted'] ?? false),
                        'isActive' => (bool)($participantData['isActive'] ?? true)
                    ];
                }
            }

            return $participants;
        } catch (\Exception $e) {
            $this->logger->error('Error getting room participants', [
                'error' => $e->getMessage(),
                'roomId' => $roomId
            ]);
            throw $e;
        }
    }
}
