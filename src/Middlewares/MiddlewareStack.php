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

    public function handle(Request $request, Response $response)
    {
        // 每次处理都创建新的中间件栈副本
        $middlewares = $this->middlewares;

        // 创建递归处理函数
        $next = function (Request $request, Response $response) use (&$middlewares, &$next) {
            if (empty($middlewares)) {
                return [
                    'type' => 'response',
                    'response' => $response
                ];
            }

            $middlewareClass = array_shift($middlewares);
            $middleware = new $middlewareClass();

            // 执行中间件
            $result = $middleware->handle($request, $response, $next);

            // 确保返回数组格式
            if (is_array($result)) {
                return $result;
            }

            return [
                'type' => 'response',
                'response' => $result
            ];
        };

        return $next($request, $response);
    }
}
