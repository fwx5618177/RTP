<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\UserService;
use App\Controllers\UserController;
use App\Http\Request;
use App\DTO\UserDTO;
use App\Entity\UserEntity;

class UserFlowTest extends TestCase
{
    private UserService $userService;
    private UserController $userController;

    protected function setUp(): void
    {
        // 设置测试数据库连接
        $this->userService = new UserService(
            $this->getContainer()->get(UserRepository::class),
            $this->getContainer()->get(EntityManagerInterface::class)
        );

        $this->userController = new UserController($this->getContainer());
    }

    public function testCompleteUserFlow()
    {
        // 1. 注册用户
        $registerData = [
            'username' => 'testflow',
            'email' => 'testflow@example.com',
            'password' => 'password123'
        ];

        $request = new Request('POST', '/users');
        $request->setBodyParams($registerData);

        $response = $this->userController->create($request);
        $this->assertEquals(200, $response->getStatusCode());

        $userData = json_decode($response->getBody(), true)['data'];
        $userId = $userData['id'];

        // 2. 获取用户信息
        $getRequest = new Request('GET', "/users/{$userId}");
        $response = $this->userController->get($getRequest, $userId);
        $this->assertEquals(200, $response->getStatusCode());

        // 3. 更新用户信息
        $updateData = [
            'firstName' => 'Updated',
            'lastName' => 'User'
        ];

        $updateRequest = new Request('PUT', "/users/{$userId}");
        $updateRequest->setBodyParams($updateData);

        $response = $this->userController->update($updateRequest, $userId);
        $this->assertEquals(200, $response->getStatusCode());

        // 4. 删除用户
        $deleteRequest = new Request('DELETE', "/users/{$userId}");
        $response = $this->userController->delete($deleteRequest, $userId);
        $this->assertEquals(204, $response->getStatusCode());

        // 5. 验证用户已删除
        $getRequest = new Request('GET', "/users/{$userId}");
        $response = $this->userController->get($getRequest, $userId);
        $this->assertEquals(404, $response->getStatusCode());
    }
}
