<?php

namespace App\DTO;

class UserDTO
{
    public function __construct(
        public ?int $id,
        public string $username,
        public string $email,
        public string $passwordHash,
        public \DateTimeImmutable $createdAt
    ) {}
}
