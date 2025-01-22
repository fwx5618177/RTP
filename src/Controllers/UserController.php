<?php

namespace App\Controllers;

use App\DTO\UserDTO;
use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;
use App\Services\UserService;
use Psr\Container\ContainerInterface;

class UserController extends BaseController
{
    private UserService $userService;
    private Logger $logger;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->userService = $container->get(UserService::class);
        $this->logger = Logger::getInstance('user-controller');
    }

    public function index(Request $request): Response
    {
        try {
            $page = (int) ($request->getQueryParams()['page'] ?? 1);
            $limit = (int) ($request->getQueryParams()['limit'] ?? 10);

            $users = $this->userService->listUsers($page, $limit);
            $total = $this->userService->countUsers();

            $userDTOs = array_map(
                fn ($user) => UserDTO::fromEntity($user)->toArray(),
                $users
            );

            return $this->successResponse([
                'users' => $userDTOs,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function create(Request $request): Response
    {
        try {
            // 记录原始请求数据
            $this->logger->info('Creating user - Raw request body', [
                'method' => $request->getMethod(),
                'headers' => $request->getHeaders(),
                'body' => $request->getBodyParams(),
                'query' => $request->getQueryParams(),
            ]);

            // 获取请求体
            $requestBody = $request->getBodyParams();

            $this->logger->info('Parsed request body', ['data' => $requestBody]);

            // 验证必填字段
            if (empty($requestBody['username']) || empty($requestBody['email']) || empty($requestBody['password'])) {
                $this->logger->error('Validation failed - Missing required fields', ['requestBody' => $requestBody]);

                return $this->errorResponse('Missing required fields');
            }

            $userDTO = new UserDTO($requestBody['username'], $requestBody['email']);
            $userDTO->password = $requestBody['password'];

            $this->logger->info('Creating user with DTO', ['dto' => $userDTO->toArray()]);

            $user = $this->userService->createUser($userDTO);

            $this->logger->info('User created successfully', ['userId' => $user->getId()]);

            return $this->successResponse([
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error creating user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse($e->getMessage());
        }
    }

    public function get(Request $request, int $id): Response
    {
        try {
            $user = $this->userService->getUserById($id);
            if (! $user) {
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
            if (! $existingUser) {
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
            if (! $user) {
                return $this->errorResponse('User not found', 404);
            }

            $this->userService->deleteUser($id);

            return $this->successResponse([
                'message' => 'User deleted successfully',
            ], 204);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
