<?php

namespace App\Repositories;

use App\DTO\UserDTO;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class UserRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, $entityManager->getClassMetadata(UserDTO::class));
    }

    public function create(UserDTO $userDTO): UserDTO
    {
        $this->_em->persist($userDTO);
        $this->_em->flush();
        return $userDTO;
    }

    public function findById(int $id): ?UserDTO
    {
        return $this->find($id);
    }

    public function usernameExists(string $username): bool
    {
        return $this->findOneBy(['username' => $username]) !== null;
    }

    public function emailExists(string $email): bool
    {
        return $this->findOneBy(['email' => $email]) !== null;
    }

    public function update(UserDTO $userDTO): UserDTO
    {
        $this->_em->persist($userDTO);
        $this->_em->flush();
        return $userDTO;
    }

    public function delete(int $id): void
    {
        $user = $this->find($id);
        if ($user) {
            $this->_em->remove($user);
            $this->_em->flush();
        }
    }
}
