<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;

class MiddlewareStack
{
    private array $middlewares;

    public function __construct(array $middlewares = [])
    {
        $this->middlewares = $middlewares;
    }

    public function handle(Request $request, Response $response): Response
    {
        // 如果没有中间件，直接返回响应
        if (empty($this->middlewares)) {
            return $response;
        }

        // 获取当前中间件
        $middlewareClass = array_shift($this->middlewares);
        $middleware = new $middlewareClass();

        // 创建next标记函数
        $nextCalled = false;
        $next = function () use (&$nextCalled) {
            $nextCalled = true;
        };

        // 执行当前中间件
        $middleware->handle($request, $response, $next);

        // 如果中间件没有调用next()，直接返回当前响应
        if (!$nextCalled) {
            return $response;
        }

        // 继续执行下一个中间件
        return $this->handle($request, $response);
    }
}
