<?php

namespace App\Routes;

use App\Http\Request;
use App\Http\Response;
use App\Middlewares\MiddlewareStack;

class Route
{
    private string $method;
    private string $path;
    private array $handler;
    private MiddlewareStack $middlewareStack;

    public function __construct(string $method, string $path, array $handler, array $middleware = [])
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
        $this->middlewareStack = new MiddlewareStack($middleware);
    }

    public function matches(Request $request): bool
    {
        $requestPath = rtrim($request->getPath(), '/');
        $routePath = rtrim($this->path, '/');
        return $this->method === $request->getMethod() &&
            $routePath === $requestPath;
    }

    public function handle(Request $request): Response
    {
        $response = new Response();

        // 执行中间件栈
        $result = $this->middlewareStack->handle($request, $response);

        if (isset($result['type']) && $result['type'] === 'response') {
            return $result['response'];
        }

        // 执行控制器
        [$controllerClass, $methodName] = $this->handler;
        $container = $request->getContainer();
        $controller = new $controllerClass($container);
        return $controller->$methodName($request, $response);
    }
}
