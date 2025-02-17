<?php

declare(strict_types=1);

namespace App\Config;

use App\Controllers\HomeController;
use App\Controllers\JanusController;
use App\Controllers\RedisController;
use App\Controllers\RoomController;
use App\Controllers\UserController;
use App\Controllers\WebSocketController;
use App\Controllers\OptionsController;
use App\Controllers\PbxController;
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
        // 会话管理
        $route->add('POST', '/session', [JanusController::class, 'createSession']);
        $route->add('DELETE', '/session/{sessionId}', [JanusController::class, 'destroySession']);

        // SIP 桥接
        $route->add('POST', '/sip/bridge/{sessionId}', [JanusController::class, 'createSipBridge']);
        $route->add('PATCH', '/sip/bridge/{sessionId}', [JanusController::class, 'updateSipBridge']);
        $route->add('DELETE', '/sip/bridge/{sessionId}', [JanusController::class, 'disconnectSipBridge']);

        // 房间管理
        $route->add('POST', '/room/join', [JanusController::class, 'joinRoom']);

        // WebRTC 信令
        $route->add('POST', '/{sessionId}/{handleId}/message', [JanusController::class, 'handleMessage']);
        $route->add('POST', '/{sessionId}/{handleId}/trickle', [JanusController::class, 'handleTrickle']);

        // OPTIONS 请求处理
        $route->add('OPTIONS', '/{sessionId}/{handleId}', [OptionsController::class, 'handle']);
        $route->add('OPTIONS', '/{sessionId}/{handleId}/trickle', [OptionsController::class, 'handle']);
    });

    // PBX 路由组
    $router->group([
        'prefix' => '/api/pbx',
        'middleware' => [],
    ], function ($route) {
        $route->add('POST', '/call', [PbxController::class, 'makeCall']);
        $route->add('GET', '/call/status/{extension}', [PbxController::class, 'getCallStatus']);
        $route->add('GET', '/channels', [PbxController::class, 'getActiveChannels']);
    });
};
