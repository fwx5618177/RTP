<?php

declare(strict_types=1);

namespace App\DTO;

class RoomDTO
{
    private string $userName;
    private array $config;

    public function __construct(string $userName, array $config = [])
    {
        if (empty(trim($userName))) {
            throw new \InvalidArgumentException('userName cannot be empty');
        }

        $this->userName = $userName;
        $this->config = $config;
    }

    public function getRoomName(): string
    {
        return $this->userName;
    }

    public function setRoomName(string $roomName): self
    {
        $this->userName = $roomName;
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
