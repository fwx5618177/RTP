<?php

namespace App\Services;

use App\DTO\UserDTO;
use App\Repositories\UserRepository;
use App\Exceptions\ValidationException;

class UserService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function createUser(UserDTO $userDTO): UserDTO
    {
        if ($this->userRepository->usernameExists($userDTO->username)) {
            throw new ValidationException('Username already exists');
        }

        if ($this->userRepository->emailExists($userDTO->email)) {
            throw new ValidationException('Email already exists');
        }

        return $this->userRepository->create($userDTO);
    }

    public function getUserById(int $id): ?UserDTO
    {
        return $this->userRepository->findById($id);
    }

    public function updateUser(UserDTO $userDTO): UserDTO
    {
        return $this->userRepository->update($userDTO);
    }

    public function deleteUser(int $id): void
    {
        $this->userRepository->delete($id);
    }
}
