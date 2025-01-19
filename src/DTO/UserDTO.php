<?php

namespace App\DTO;

use App\Entity\UserEntity;
use App\Http\Request;
use App\Validator\Validator;
use DateTime;

class UserDTO extends BaseDTO
{
    public function __construct(
        public string $email,
        public string $username,
        public ?string $password = null,
        public ?int $id = null,
        public ?string $uuid = null,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $avatarUrl = null,
        public ?bool $isActive = true,
        public ?array $roles = ['user'],
        public ?string $role = 'user',
        public ?string $lastLoginAt = null,
        public ?string $emailVerifiedAt = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?string $deletedAt = null
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
            $data['email'],
            $data['username'],
            $data['password'],
            null,
            null,
            $data['firstName'] ?? null,
            $data['lastName'] ?? null,
            $data['phone'] ?? null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null
        );
    }

    public static function fromEntity(UserEntity $user): self
    {
        return new self(
            $user->getEmail(),
            $user->getUsername(),
            null,
            $user->getId(),
            $user->getUuid(),
            $user->getFirstName(),
            $user->getLastName(),
            $user->getPhone(),
            null,
            null,
            $user->isActive(),
            $user->getRoles(),
            $user->getRole(),
            $user->getLastLoginAt() ? $user->getLastLoginAt()->format('Y-m-d H:i:s') : null,
            null,
            $user->getCreatedAt() ? $user->getCreatedAt()->format('Y-m-d H:i:s') : null,
            $user->getUpdatedAt() ? $user->getUpdatedAt()->format('Y-m-d H:i:s') : null,
            $user->getDeletedAt() ? $user->getDeletedAt()->format('Y-m-d H:i:s') : null
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
            $user->setLastLoginAt($this->lastLoginAt instanceof \DateTime
                ? $this->lastLoginAt
                : new \DateTime($this->lastLoginAt));
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
            ? new DateTime($data['createdAt'])
            : null;

        $updatedAt = isset($data['updatedAt'])
            ? new DateTime($data['updatedAt'])
            : null;

        $lastLoginAt = isset($data['lastLoginAt'])
            ? new DateTime($data['lastLoginAt'])
            : null;

        $deletedAt = isset($data['deletedAt'])
            ? new DateTime($data['deletedAt'])
            : null;

        return new self(
            $data['email'],
            $data['username'],
            $data['password'] ?? null,
            $data['id'] ?? null,
            $data['uuid'] ?? null,
            $data['firstName'] ?? null,
            $data['lastName'] ?? null,
            $data['phone'] ?? null,
            null,
            null,
            $data['isActive'] ?? true,
            $data['roles'] ?? ['ROLE_USER'],
            $data['role'] ?? 'user',
            $lastLoginAt ? $lastLoginAt->format('Y-m-d H:i:s') : null,
            null,
            $createdAt ? $createdAt->format('Y-m-d H:i:s') : null,
            $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : null,
            $deletedAt ? $deletedAt->format('Y-m-d H:i:s') : null
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
            $user->setLastLoginAt($this->lastLoginAt instanceof \DateTime
                ? $this->lastLoginAt
                : new \DateTime($this->lastLoginAt));
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

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt ? new DateTime($this->createdAt) : null;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt ? new DateTime($this->updatedAt) : null;
    }

    public function getLastLoginAt(): ?DateTime
    {
        return $this->lastLoginAt ? new DateTime($this->lastLoginAt) : null;
    }

    public function getDeletedAt(): ?DateTime
    {
        return $this->deletedAt ? new DateTime($this->deletedAt) : null;
    }
}
