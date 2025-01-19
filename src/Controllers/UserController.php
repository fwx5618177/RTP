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
        // 获取用户列表的实现
        return $this->successResponse([
            'message' => 'User list endpoint',
        ]);
    }

    public function create(Request $request): Response
    {
        try {
            $userDTO = UserDTO::fromRequest($request);
            $user = $this->userService->createUser($userDTO);
            return $this->successResponse(UserDTO::fromEntity($user)->toArray());
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function get(Request $request, int $id): Response
    {
        try {
            $user = $this->userService->getUserById($id);
            if (!$user) {
                return $this->errorResponse('User not found', 404);
            }
            return $this->successResponse(UserDTO::fromEntity($user)->toArray());
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function update(Request $request, int $id): Response
    {
        try {
            $data = $request->getBodyParams();
            $userDTO = UserDTO::fromArray(array_merge($data, ['id' => $id]));
            $user = $this->userService->updateUser($userDTO);
            return $this->successResponse(UserDTO::fromEntity($user)->toArray());
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function delete(Request $request, int $id): Response
    {
        try {
            $this->userService->deleteUser($id);
            return $this->successResponse([
                'message' => 'User deleted successfully',
                'user' => [],
            ], 204);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
