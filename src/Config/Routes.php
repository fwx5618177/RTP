<?php

declare(strict_types=1);

namespace App\Config;

use App\Controllers\HomeController;
use App\Controllers\JanusController;
use App\Controllers\RedisController;
use App\Controllers\RoomController;
use App\Controllers\UserController;
use App\Controllers\WebSocketController;
use App\Http\Response;
use App\Middlewares\CorsMiddleware;
use App\Middlewares\TestConditionMiddleware;
use App\Middlewares\TestFlowMiddleware;
use App\Routes\Router;

return function (Router $router) {
    // 添加全局中间件
    $router->addGlobalMiddleware(new TestFlowMiddleware());
    $router->addGlobalMiddleware(new CorsMiddleware());

    // 基础路由
    $router->add('GET', '/', [HomeController::class, 'index'], [new TestConditionMiddleware()]);

    $router->group([
        'prefix' => '/api/test',
        'middleware' => [new TestConditionMiddleware()],
    ], function ($route) {
        $route->add('GET', '/', [HomeController::class, 'index']);
    });

    // 用户相关路由组
    $router->group([
        'prefix' => '/api/users',
        'middleware' => [new TestConditionMiddleware()],
    ], function ($route) {
        $route->add('GET', '/', [UserController::class, 'index']);
        $route->add('POST', '/', [UserController::class, 'create']);
        $route->add('GET', '/{id}', [UserController::class, 'get']);
        $route->add('PUT', '/{id}', [UserController::class, 'update']);
        $route->add('DELETE', '/{id}', [UserController::class, 'delete']);
    });

    $router->group([
        'prefix' => '/api/redis',
        'middleware' => [new TestConditionMiddleware()],
    ], function ($route) {
        $route->add('POST', '/set', [RedisController::class, 'set']);
        $route->add('GET', '/get/{key}', [RedisController::class, 'get']);
        $route->add('GET', '/exists/{key}', [RedisController::class, 'exists']);
        $route->add('DELETE', '/delete/{key}', [RedisController::class, 'delete']);
    });

    // WebSocket 路由组
    $router->group([
        'prefix' => '/api/ws',
        'middleware' => [new TestConditionMiddleware()],
    ], function ($route) {
        $route->add('GET', '/connect', [WebSocketController::class, 'connect']);
    });

    // 房间相关路由组
    $router->group([
        'prefix' => '/api/rooms',
        'middleware' => [],
    ], function ($route) {
        // 房间基本操作
        $route->add('POST', '/', [RoomController::class, 'createRoom']);
        $route->add('GET', '/{roomId}', [RoomController::class, 'getRoomDetails']);
        $route->add('POST', '/join', [RoomController::class, 'joinRoom']);
        $route->add('POST', '/leave', [RoomController::class, 'leaveRoom']);

        // 房间参与者
        $route->add('GET', '/{roomId}/participants', [RoomController::class, 'getRoomParticipants']);

        // SIP 相关
        $route->add('POST', '/sip', [RoomController::class, 'joinRoom']);
    });

    // Janus WebRTC 相关路由组
    $router->group([
        'prefix' => '/api/janus',
        'middleware' => [],
    ], function ($route) {
        $route->add('POST', '/{sessionId}/{handleId}', [JanusController::class, 'handleMessage']);
        $route->add('POST', '/{sessionId}/{handleId}/trickle', [JanusController::class, 'handleTrickle']);
    });
};
