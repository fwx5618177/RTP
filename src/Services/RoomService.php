<?php

declare(strict_types=1);

namespace App\Services;

use App\Repository\RoomRepository;
use App\Entity\RoomEntity;
use App\DTO\RoomDTO;
use App\Validator\Validator;
use App\Services\RedisService;
use App\Exceptions\RoomException;
use App\Logs\Logger;
use App\Utils\Container;
use Ramsey\Uuid\Uuid;

class RoomService extends BaseService
{
    private Logger $logger;

    public function __construct(
        protected RoomRepository $roomRepository,
        protected Validator $validator,
        protected RedisService $redisService
    ) {
        parent::__construct();
        $this->logger = Container::getInstance()->get(Logger::class);

        $this->roomRepository = $roomRepository;
        $this->validator = $validator;
        $this->redisService = $redisService;
    }

    public function createRoom(RoomDTO $roomDTO): RoomEntity
    {
        $this->logger->info('Starting room creation', ['roomName' => $roomDTO->getRoomName()]);

        // Validate DTO
        $data = [
            'roomName' => $roomDTO->getRoomName(),
            'config' => $roomDTO->getConfig()
        ];

        $rules = [
            'roomName' => 'required|string|min:3|max:50',
            'config' => 'required|array'
        ];

        $this->validator->validate($data, $rules);

        // Generate room ID
        $roomId = $this->generateRoomId();

        // Create room entity
        $room = new RoomEntity(
            $roomId,
            $roomDTO->getRoomName(),
            $roomDTO->getConfig()
        );

        // Save to MySQL
        $room = $this->roomRepository->save($room);

        // Initialize Redis data
        $this->initializeRedisRoomData($room);

        // Cache the room
        $this->cacheRoom($room);

        return $room;
    }

    private function initializeRedisRoomData(RoomEntity $room): void
    {
        $roomId = $room->getRoomId();

        // Set room metadata
        $this->redisService->hSet(
            "room:$roomId:metadata",
            [
                'created_at' => $room->getCreatedAt()->format('Y-m-d H:i:s'),
                'config' => json_encode($room->getConfig()),
                'status' => 'active'
            ]
        );

        // Initialize participants set
        $this->redisService->sAdd("room:$roomId:participants", [$room->getRoomName()]);
    }

    private function cacheRoom(RoomEntity $room): void
    {
        $roomData = [
            'id' => $room->getId(),
            'roomId' => $room->getRoomId(),
            'name' => $room->getRoomName(),
            // ... other fields
        ];

        $this->redisService->set("room:{$room->getRoomId()}", json_encode($roomData));
    }

    public function joinRoom(string $roomId, string $userId): array
    {
        // Check if room exists in Redis
        if (!$this->redisService->exists("room:$roomId:metadata")) {
            throw new RoomException('Room not found');
        }

        // Add user to participants
        $this->redisService->sAdd("room:$roomId:participants", [$userId]);

        // Get room metadata
        $metadata = $this->redisService->hGetAll("room:$roomId:metadata");

        return [
            'roomId' => $roomId,
            'metadata' => $metadata,
            'participants' => $this->redisService->sMembers("room:$roomId:participants")
        ];
    }

    public function leaveRoom(string $roomId, string $userId): void
    {
        // Check if room exists in Redis
        if (!$this->redisService->exists("room:$roomId:metadata")) {
            throw new RoomException('Room not found');
        }

        // Remove user from participants
        $this->redisService->sRem("room:$roomId:participants", $userId);

        // Check if room is empty
        $participantCount = $this->redisService->sCard("room:$roomId:participants");
        if ($participantCount === 0) {
            $this->cleanupRoom($roomId);
        }
    }

    private function cleanupRoom(string $roomId): void
    {
        // Delete Redis keys
        $this->redisService->del([
            "room:$roomId:metadata",
            "room:$roomId:participants"
        ]);

        // Delete from MySQL
        $this->roomRepository->deleteRoom($roomId);
    }

    private function generateRoomId(): string
    {
        return Uuid::uuid4()->toString();
    }

    public function handleSipCall(array $sipHeaders, string $roomId): void
    {
        // Check if room exists
        if (!$this->redisService->exists("room:$roomId:metadata")) {
            throw new RoomException('Room not found');
        }

        // Add SIP participant
        $sipId = $sipHeaders['X-Conference-Server'] . ':' . $sipHeaders['X-Conference-Room'];
        $this->redisService->sAdd("room:$roomId:participants", [$sipId]);

        // TODO: Implement RTP bridge to Janus
    }
}
