<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\DTO\UserDTO;
use App\Services\UserService;
use App\Exceptions\ValidationException;
use Psr\Container\ContainerInterface;

class UserController extends BaseController
{
    private UserService $userService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->userService = $container->get(UserService::class);
    }

    public function index(Request $request): Response
    {
        // 临时返回测试数据
        return $this->successResponse([
            'message' => 'User list endpoint',
            'status' => 'success'
        ]);
    }

    public function create(Request $request): Response
    {
        try {
            // 1. 请求验证和转换为 DTO
            $userDTO = UserDTO::fromRequest($request);

            // 2. 调用 Service 处理业务逻辑
            $user = $this->userService->createUser($userDTO);

            // 3. 转换响应
            return $this->successResponse(UserDTO::fromEntity($user));
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
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
