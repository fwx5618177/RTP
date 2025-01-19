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
        try {
            $page = (int) ($request->getQueryParams()['page'] ?? 1);
            $limit = (int) ($request->getQueryParams()['limit'] ?? 10);

            $users = $this->userService->listUsers($page, $limit);
            $total = $this->userService->countUsers();

            $userDTOs = array_map(
                fn($user) => UserDTO::fromEntity($user)->toArray(),
                $users
            );

            return $this->successResponse([
                'users' => $userDTOs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
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
            $existingUser = $this->userService->getUserById($id);
            if (!$existingUser) {
                return $this->errorResponse('User not found', 404);
            }

            // 从请求创建 DTO，并保留现有用户的一些数据
            $data = array_merge(
                UserDTO::fromEntity($existingUser)->toArray(),
                $request->getBodyParams()
            );
            $data['id'] = $id; // 确保 ID 正确

            $userDTO = UserDTO::fromArray($data);

            // 更新现有实体
            $updatedUser = $userDTO->updateEntity($existingUser);
            $this->userService->updateUser($updatedUser);

            return $this->successResponse(UserDTO::fromEntity($updatedUser)->toArray());
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function delete(Request $request, int $id): Response
    {
        try {
            $user = $this->userService->getUserById($id);
            if (!$user) {
                return $this->errorResponse('User not found', 404);
            }

            $this->userService->deleteUser($id);
            return $this->successResponse([
                'message' => 'User deleted successfully'
            ], 204);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
