<?php

namespace App\Services;

use App\DTO\UserDTO;
use App\Entity\UserEntity;
use App\Repository\UserRepository;
use App\Exceptions\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use App\Logs\Logger;

class UserService extends BaseService
{
    private Logger $logger;

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->logger = Logger::getInstance('user-service');
    }

    public function registerUser(UserDTO $dto): UserEntity
    {
        $this->logger->info('Starting user registration', ['email' => $dto->getEmail()]);

        $this->entityManager->beginTransaction();
        try {
            // 检查唯一性
            if ($this->userRepository->findByEmail($dto->getEmail())) {
                throw new ValidationException('Email already exists');
            }

            // 创建用户
            $user = $dto->toEntity();

            // 保存
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('User registered successfully', ['id' => $user->getId()]);
            return $user;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('User registration failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getUserById(int $id): ?UserEntity
    {
        $this->logger->debug('Fetching user by ID', ['id' => $id]);
        return $this->userRepository->find($id);
    }

    public function getUserByEmail(string $email): ?UserEntity
    {
        $this->logger->debug('Fetching user by email', ['email' => $email]);
        return $this->userRepository->findByEmail($email);
    }

    public function updateUser(UserEntity $user): UserEntity
    {
        $this->logger->info('Updating user', ['id' => $user->getId()]);

        $this->entityManager->beginTransaction();
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $user;
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('User update failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function deleteUser(int $id): void
    {
        $this->logger->info('Deleting user', ['id' => $id]);

        $this->entityManager->beginTransaction();
        try {
            $user = $this->getUserById($id);
            if (!$user) {
                throw new ValidationException('User not found');
            }

            $this->entityManager->remove($user);
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            $this->logger->error('User deletion failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function listUsers(int $page = 1, int $limit = 10): array
    {
        $this->logger->debug('Listing users', ['page' => $page, 'limit' => $limit]);
        return $this->userRepository->findAll($page, $limit);
    }

    public function countUsers(): int
    {
        return $this->userRepository->count([]);
    }

    // 不需要事务的方法
    public function getUserProfile(int $id): ?array
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return null;
        }

        return [
            'uuid' => $user->getUuid(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFirstName() . ' ' . $user->getLastName(),
            'isActive' => $user->isActive(),
            'lastLogin' => $user->getLastLoginAt()?->format('Y-m-d H:i:s')
        ];
    }

    public function createUser(UserDTO $dto): UserEntity
    {
        // 检查邮箱是否已存在
        if ($this->userRepository->findByEmail($dto->getEmail())) {
            throw new ValidationException('Email already exists');
        }

        // 创建用户实体
        $user = $dto->toEntity();

        // 保存用户
        $this->userRepository->save($user);

        return $user;
    }
}
