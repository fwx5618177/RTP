<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoomRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\Table(name: 'rooms')]
#[ORM\HasLifecycleCallbacks]
class RoomEntity extends BaseEntity
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(name: 'uuid', type: 'string', length: 36, unique: true)]
    private string $uuid;

    #[ORM\Column(name: 'room_id', type: 'string', length: 36, unique: true)]
    private string $roomId;

    #[ORM\Column(name: 'room_name', type: 'string', length: 255)]
    private string $roomName;

    #[ORM\Column(type: 'json')]
    private array $config = [];

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    private DateTime $updatedAt;

    #[ORM\Column(name: 'deleted_at', type: 'datetime', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        string $roomId,
        string $roomName,
        array $config = []
    ) {
        parent::__construct();
        $this->uuid = Uuid::uuid4()->toString();
        $this->roomId = $roomId;
        $this->roomName = $roomName;
        $this->config = $config;
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();

        $this->logOperation('created');
    }

    // 生命周期回调
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTime();
        $this->logOperation('updated');
    }

    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->logOperation('loaded');
    }

    #[ORM\PreRemove]
    public function onPreRemove(): void
    {
        $this->handlePreDelete();
        $this->logOperation('deleted');
    }

    private function handlePreDelete(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getRoomId(): string
    {
        return $this->roomId;
    }

    public function setRoomId(string $roomId): self
    {
        $this->roomId = $roomId;
        return $this;
    }

    public function getRoomName(): string
    {
        return $this->roomName;
    }

    public function setRoomName(string $roomName): self
    {
        $this->roomName = $roomName;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): self
    {
        $this->config = $config;
        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function delete(): self
    {
        $this->handlePreDelete();
        return $this;
    }

    public function restore(): self
    {
        $this->deletedAt = null;
        $this->logOperation('restored');
        return $this;
    }

    public function updateTimestamp(): void
    {
        $this->updatedAt = new DateTime();
    }
}
