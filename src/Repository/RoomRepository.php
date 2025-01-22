<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RoomEntity;
use Doctrine\ORM\EntityManager;
use App\Services\RedisService;
use App\Utils\Container;
use App\Exceptions\RoomException;
use Doctrine\DBAL\Exception as DBALException;
use App\Utils\DBConnectionPool;
use App\Repository\BaseRepository;

class RoomRepository extends BaseRepository
{
    private RedisService $redisService;

    public function __construct(EntityManager $em)
    {
        parent::__construct($em, $em->getClassMetadata(RoomEntity::class));
        // 从容器中获取 RedisService
        $this->redisService = Container::getInstance()->get(RedisService::class);
    }

    public function createRoom(RoomEntity $room): RoomEntity
    {
        $connection = null;
        try {
            // 从连接池获取连接
            $connection = $this->connectionPool->getConnection();

            // 开始事务
            $connection->beginTransaction();

            $em = $this->getEntityManager();
            $em->persist($room);
            $em->flush();

            // 缓存到Redis
            $this->redisService->set(
                "room:{$room->getRoomId()}",
                json_encode([
                    'id' => $room->getId(),
                    'roomId' => $room->getRoomId(),
                    'roomName' => $room->getRoomName(),
                    'config' => $room->getConfig(),
                    'participants' => [],
                    'createdAt' => $room->getCreatedAt()->format('Y-m-d H:i:s')
                ])
            );

            // 提交事务
            $connection->commit();

            return $room;
        } catch (DBALException $e) {
            // 回滚事务
            if ($connection && $connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw new RoomException('Failed to create room: ' . $e->getMessage(), 0, $e);
        } finally {
            // 归还连接到连接池
            if ($connection) {
                $this->connectionPool->releaseConnection($connection);
            }
        }
    }

    public function getRoom(string $roomId): ?RoomEntity
    {
        return $this->getEntityManager()->getRepository(RoomEntity::class)
            ->findOneBy(['roomId' => $roomId]);
    }

    public function deleteRoom(string $roomId): void
    {
        $room = $this->getRoom($roomId);
        if ($room) {
            $this->getEntityManager()->remove($room);
            $this->getEntityManager()->flush();
        }

        // Remove from Redis
        $this->redisService->delete("room:{$roomId}");
    }

    public function addParticipant(string $roomId, string $userId): void
    {
        $roomData = $this->redisService->get("room:{$roomId}");
        if ($roomData) {
            $roomData = json_decode($roomData, true);
            $roomData['participants'][] = $userId;
            $this->redisService->set("room:{$roomId}", json_encode($roomData));
        }
    }

    public function removeParticipant(string $roomId, string $userId): void
    {
        $roomData = $this->redisService->get("room:{$roomId}");
        if ($roomData) {
            $roomData = json_decode($roomData, true);
            $roomData['participants'] = array_filter(
                $roomData['participants'],
                fn($participant) => $participant !== $userId
            );
            $this->redisService->set("room:{$roomId}", json_encode($roomData));
        }
    }

    public function getRoomParticipants(string $roomId): array
    {
        return $this->executeSafely(function ($em) use ($roomId) {
            $result = $this->createQueryBuilder('r')
                ->select('r.participants')
                ->where('r.roomId = :roomId')
                ->setParameter('roomId', $roomId)
                ->getQuery()
                ->getOneOrNullResult();

            return $result['participants'] ?? [];
        });
    }

    public function findByRoomId(string $roomId): ?RoomEntity
    {
        return $this->executeSafely(function ($em) use ($roomId) {
            return $this->findOneBy(['roomId' => $roomId]);
        });
    }

    public function save(RoomEntity $room): RoomEntity
    {
        return $this->executeInTransaction(function ($em) use ($room) {
            $em->persist($room);
            $em->flush();
            return $room;
        });
    }

    public function delete(RoomEntity $room): void
    {
        $this->executeInTransaction(function ($em) use ($room) {
            $em->remove($room);
            $em->flush();
        });
    }
}
