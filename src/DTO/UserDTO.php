<?php

namespace App\DTO;

use App\Entity\UserEntity;
use App\Http\Request;
use App\Validator\Validator;

class UserDTO
{
    public function __construct(
        private ?int $id = null,
        private ?string $uuid = null,
        private string $username,
        private string $email,
        private ?string $password = null,
        private ?string $passwordHash = null,
        private ?string $firstName = null,
        private ?string $lastName = null,
        private ?string $phone = null,
        private array $roles = ['ROLE_USER'],
        private bool $isActive = true,
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
        private ?\DateTimeImmutable $lastLoginAt = null,
        private ?\DateTimeImmutable $deletedAt = null
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = $request->getBodyParams();

        // 验证请求数据
        $validator = new Validator();
        $validator->validate($data, [
            'username' => 'required|string|min:3',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'firstName' => 'string|nullable',
            'lastName' => 'string|nullable',
            'phone' => 'string|nullable|max:20'
        ]);

        return new self(
            null,
            null,
            $data['username'],
            $data['email'],
            $data['password'],
            null,
            $data['firstName'] ?? null,
            $data['lastName'] ?? null,
            $data['phone'] ?? null
        );
    }

    public static function fromEntity(UserEntity $user): self
    {
        return new self(
            $user->getId(),
            $user->getUuid(),
            $user->getUsername(),
            $user->getEmail(),
            null,
            $user->getPasswordHash(),
            $user->getFirstName(),
            $user->getLastName(),
            $user->getPhone(),
            $user->getRoles(),
            $user->isActive(),
            $user->getCreatedAt(),
            $user->getUpdatedAt(),
            $user->getLastLoginAt(),
            $user->getDeletedAt()
        );
    }

    public function toEntity(): UserEntity
    {
        $user = new UserEntity(
            $this->username,
            $this->email,
            $this->passwordHash ?? password_hash($this->password, PASSWORD_DEFAULT)
        );

        if ($this->firstName !== null) {
            $user->setFirstName($this->firstName);
        }

        if ($this->lastName !== null) {
            $user->setLastName($this->lastName);
        }

        if ($this->phone !== null) {
            $user->setPhone($this->phone);
        }

        if ($this->roles !== ['ROLE_USER']) {
            $user->setRoles($this->roles);
        }

        if (!$this->isActive) {
            $user->setIsActive(false);
        }

        if ($this->lastLoginAt !== null) {
            $user->setLastLoginAt($this->lastLoginAt);
        }

        return $user;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'username' => $this->username,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'phone' => $this->phone,
            'roles' => $this->roles,
            'isActive' => $this->isActive,
            'fullName' => trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? '')),
            'createdAt' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'lastLoginAt' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'deletedAt' => $this->deletedAt?->format('Y-m-d H:i:s')
        ];
    }

    public static function fromArray(array $data): self
    {
        // 验证数据
        $validator = new Validator();
        $validator->validate($data, [
            'id' => 'integer|nullable',
            'uuid' => 'string|nullable',
            'username' => 'required|string|min:3',
            'email' => 'required|email',
            'password' => 'string|nullable|min:6',
            'firstName' => 'string|nullable',
            'lastName' => 'string|nullable',
            'phone' => 'string|nullable|max:20',
            'roles' => 'array|nullable',
            'isActive' => 'boolean|nullable'
        ]);

        // 处理日期时间字段
        $createdAt = isset($data['createdAt'])
            ? new \DateTimeImmutable($data['createdAt'])
            : null;

        $updatedAt = isset($data['updatedAt'])
            ? new \DateTimeImmutable($data['updatedAt'])
            : null;

        $lastLoginAt = isset($data['lastLoginAt'])
            ? new \DateTimeImmutable($data['lastLoginAt'])
            : null;

        $deletedAt = isset($data['deletedAt'])
            ? new \DateTimeImmutable($data['deletedAt'])
            : null;

        return new self(
            $data['id'] ?? null,
            $data['uuid'] ?? null,
            $data['username'],
            $data['email'],
            $data['password'] ?? null,
            $data['passwordHash'] ?? null,
            $data['firstName'] ?? null,
            $data['lastName'] ?? null,
            $data['phone'] ?? null,
            $data['roles'] ?? ['ROLE_USER'],
            $data['isActive'] ?? true,
            $createdAt,
            $updatedAt,
            $lastLoginAt,
            $deletedAt
        );
    }

    /**
     * 用于更新现有实体
     */
    public function updateEntity(UserEntity $user): UserEntity
    {
        // 更新基本信息
        $user->setUsername($this->username)
            ->setEmail($this->email);

        // 如果提供了新密码，则更新密码
        if ($this->password !== null) {
            $user->setPasswordHash(password_hash($this->password, PASSWORD_DEFAULT));
        }

        // 更新可选字段
        if ($this->firstName !== null) {
            $user->setFirstName($this->firstName);
        }

        if ($this->lastName !== null) {
            $user->setLastName($this->lastName);
        }

        if ($this->phone !== null) {
            $user->setPhone($this->phone);
        }

        if ($this->roles !== ['ROLE_USER']) {
            $user->setRoles($this->roles);
        }

        if ($this->isActive !== null) {
            $user->setIsActive($this->isActive);
        }

        if ($this->lastLoginAt !== null) {
            $user->setLastLoginAt($this->lastLoginAt);
        }

        return $user;
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
