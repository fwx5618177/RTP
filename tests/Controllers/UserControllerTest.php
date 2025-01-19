<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\UserController;
use App\Http\Request;
use App\Http\Response;
use App\Services\UserService;
use App\DTO\UserDTO;
use Psr\Container\ContainerInterface;

class UserControllerTest extends TestCase
{
    private UserController $controller;
    private UserService $userService;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->userService = $this->createMock(UserService::class);
        $this->container = $this->createMock(ContainerInterface::class);

        $this->container->method('get')
            ->with(UserService::class)
            ->willReturn($this->userService);

        $this->controller = new UserController($this->container);
    }

    public function testCreateSuccess()
    {
        $requestData = [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $request = new Request('POST', '/users', [], [], [], []);
        $request->setBodyParams($requestData);

        $this->userService->expects($this->once())
            ->method('createUser')
            ->willReturn(new UserEntity('testuser', 'test@example.com', 'hash'));

        $response = $this->controller->create($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('data', json_decode($response->getBody(), true));
    }
}
