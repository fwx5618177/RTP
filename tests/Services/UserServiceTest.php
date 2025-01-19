<?php

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use App\Services\UserService;
use App\DTO\UserDTO;
use App\Entity\UserEntity;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Exceptions\ValidationException;

class UserServiceTest extends TestCase
{
    private UserService $userService;
    private UserRepository $userRepository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userService = new UserService($this->userRepository, $this->entityManager);
    }

    public function testRegisterUserSuccess()
    {
        $dto = new UserDTO(
            username: 'testuser',
            email: 'test@example.com',
            password: 'password123'
        );

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->entityManager->expects($this->once())
            ->method('commit');

        $user = $this->userService->registerUser($dto);

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertEquals('testuser', $user->getUsername());
        $this->assertEquals('test@example.com', $user->getEmail());
    }

    public function testRegisterUserWithExistingEmail()
    {
        $dto = new UserDTO(
            username: 'testuser',
            email: 'existing@example.com',
            password: 'password123'
        );

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with('existing@example.com')
            ->willReturn(new UserEntity('existinguser', 'existing@example.com', 'hash'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Email already exists');

        $this->userService->registerUser($dto);
    }
}
