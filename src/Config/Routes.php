<?php

declare(strict_types=1);

namespace App\Config;

use App\Controllers\HomeController;
use App\Controllers\UserController;
use App\Controllers\RedisController;
use App\Middlewares\TestConditionMiddleware;
use App\Middlewares\TestFlowMiddleware;
use App\Routes\Router;

return function (Router $router) {
    // 添加全局中间件
    $router->addGlobalMiddleware(new TestFlowMiddleware());

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
};
