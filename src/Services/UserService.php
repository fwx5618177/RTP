<?php

namespace App\Services;

use App\DTO\UserDTO;
use Doctrine\ORM\EntityManager;

class UserService
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getUsers()
    {
        // 临时返回测试数据
        return [];
    }

    public function createUser(UserDTO $userDTO)
    {
        // 临时实现
        return null;
    }

    public function getUserById(int $id)
    {
        // 临时实现
        return null;
    }

    public function updateUser(UserDTO $userDTO)
    {
        // 临时实现
        return null;
    }

    public function deleteUser(int $id)
    {
        // 临时实现
        return true;
    }
}
