<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;
use Swoole\Coroutine;

class MiddlewareStack
{
    private array $middlewares;

    public function __construct(array $middlewares = [])
    {
        $this->middlewares = $middlewares;
    }

    public function handle(Request $request, Response $response)
    {
        // 在协程环境下运行中间件
        if (Coroutine::getCid() > 0) {
            return $this->handleInCoroutine($request, $response);
        }

        return $this->handleSync($request, $response);
    }

    private function handleInCoroutine(Request $request, Response $response)
    {
        $middlewares = $this->middlewares;

        $next = function (Request $request, Response $response) use (&$middlewares, &$next) {
            if (empty($middlewares)) {
                return [
                    'type' => 'response',
                    'response' => $response
                ];
            }

            $middlewareClass = array_shift($middlewares);
            $middleware = new $middlewareClass();

            // 在协程中执行中间件
            $result = Coroutine::create(function () use ($middleware, $request, $response, $next) {
                return $middleware->handle($request, $response, $next);
            });

            // 等待协程完成
            $result = Coroutine::yield($result);

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

    private function handleSync(Request $request, Response $response)
    {
        $middlewares = $this->middlewares;

        $next = function (Request $request, Response $response) use (&$middlewares, &$next) {
            if (empty($middlewares)) {
                return [
                    'type' => 'response',
                    'response' => $response
                ];
            }

            $middlewareClass = array_shift($middlewares);
            $middleware = new $middlewareClass();

            // 同步执行中间件
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
