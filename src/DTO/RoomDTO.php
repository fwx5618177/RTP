<?php

declare(strict_types=1);

namespace App\DTO;

class RoomDTO
{
    private string $userId;
    private string $roomName;
    private array $config;

    public function __construct(string $userId, string $roomName, array $config = [])
    {
        if (empty(trim($userId))) {
            throw new \InvalidArgumentException('userId cannot be empty');
        }

        if (empty(trim($roomName))) {
            throw new \InvalidArgumentException('roomName cannot be empty');
        }

        $this->userId = $userId;
        $this->roomName = $roomName;
        $this->config = $config;
    }

    public function getRoomName(): string
    {
        return $this->roomName;
    }

    public function getUserId(): string
    {
        return $this->userId;
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
}
