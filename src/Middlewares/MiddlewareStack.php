<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;

class MiddlewareStack
{
    private array $middlewares = [];
    private int $currentIndex = 0;

    public function add($middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function handle(Request $request, Response $response): array
    {
        // 这个方法是为了兼容旧代码，实际使用 process 方法
        $result = $this->process($request, $response);

        // 如果是中间件返回的响应，包装成特定格式
        if ($result instanceof Response) {
            return [
                'type' => 'response',
                'response' => $result,
            ];
        }

        // 如果没有中间件返回响应，允许继续处理
        return [
            'type' => 'continue',
            'request' => $request,
            'response' => $response,
        ];
    }

    public function process(Request $request, Response $response): Response
    {
        $next = function (Request $request, Response $response) {
            if ($this->currentIndex >= count($this->middlewares)) {
                return $response;
            }

            $middleware = $this->middlewares[$this->currentIndex];
            $this->currentIndex++;

            // 如果是字符串类名，实例化中间件
            if (is_string($middleware)) {
                $middleware = new $middleware();
            }

            return $middleware->process($request, $response, function (Request $req, Response $res) {
                return $this->process($req, $res);
            });
        };

        return $next($request, $response);
    }
}
