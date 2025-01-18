<?php

namespace App\DTO;

use App\DTO\BaseDTO;
use App\Request\Request;
use App\Validator\Validator;
use App\Entity\User;

class UserDTO extends BaseDTO
{
    public static function fromRequest(Request $request): self 
    {
        $data = $request->getBodyParams();
        
        // 验证请求数据
        $validator = new Validator();
        $validator->validate($data, [
            'username' => 'required|string|min:3',
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);
        
        return new self(
            null,
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            new \DateTimeImmutable()
        );
    }

    public static function fromEntity(User $user): self 
    {
        return new self(
            $user->getId(),
            $user->getUsername(),
            $user->getEmail(),
            $user->getPasswordHash(),
            $user->getCreatedAt()
        );
    }
}
