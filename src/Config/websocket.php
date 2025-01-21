<?php

use App\Controllers\WebSocketController;
use App\Routes\WebSocketRouter;

return function (WebSocketRouter $router) {
    // 基础消息路由
    $router->addRoute('/message', [WebSocketController::class, 'handleMessage']);

    // 心跳路由
    $router->addRoute('/ping', [WebSocketController::class, 'handlePing']);

    // 广播路由
    $router->addRoute('/broadcast', [WebSocketController::class, 'handleBroadcast']);

    // 私聊路由
    $router->addRoute('/private', [WebSocketController::class, 'handlePrivateMessage']);

    // 系统路由
    $router->addRoute('/handshake', [WebSocketController::class, 'handleHandshake']);
    $router->addRoute('/close', [WebSocketController::class, 'handleClose']);
};
