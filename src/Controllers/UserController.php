<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\DTO\UserDTO;
use App\Services\UserService;
use App\Exceptions\ValidationException;

class UserController
{
    public function __construct(
        private UserService $userService
    ) {}

    public function create(Request $request): Response
    {
        $data = $request->getBodyParams();

        try {
            $userDTO = new UserDTO(
                null,
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                new \DateTimeImmutable()
            );

            $createdUser = $this->userService->createUser($userDTO);

            return new Response(201, ['Content-Type' => 'application/json'], [
                'id' => $createdUser->id,
                'username' => $createdUser->username,
                'email' => $createdUser->email,
                'createdAt' => $createdUser->createdAt->format('Y-m-d H:i:s')
            ]);
        } catch (ValidationException $e) {
            return new Response(400, ['Content-Type' => 'application/json'], [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function get(Request $request, int $id): Response
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            return new Response(404, ['Content-Type' => 'application/json'], [
                'error' => 'User not found'
            ]);
        }

        return new Response(200, ['Content-Type' => 'application/json'], [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'createdAt' => $user->createdAt->format('Y-m-d H:i:s')
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $data = $request->getBodyParams();

        try {
            $userDTO = new UserDTO(
                $id,
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                new \DateTimeImmutable()
            );

            $updatedUser = $this->userService->updateUser($userDTO);

            return new Response(200, ['Content-Type' => 'application/json'], [
                'id' => $updatedUser->id,
                'username' => $updatedUser->username,
                'email' => $updatedUser->email,
                'createdAt' => $updatedUser->createdAt->format('Y-m-d H:i:s')
            ]);
        } catch (ValidationException $e) {
            return new Response(400, ['Content-Type' => 'application/json'], [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function delete(Request $request, int $id): Response
    {
        $this->userService->deleteUser($id);
        return new Response(204);
    }
}
