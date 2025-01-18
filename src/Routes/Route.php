<?php

namespace App\Routes;

use App\Http\Request;
use App\Http\Response;

class Route
{
    private string $method;
    private string $path;
    private array $handler;
    private array $middlewares = [];

    public function __construct(string $method, string $path, array $handler, array $middlewares = [])
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
        $this->middlewares = $middlewares;
    }

    public function matches(Request $request): bool
    {
        $requestPath = rtrim($request->getPath(), '/');
        $routePath = rtrim($this->path, '/');
        return $this->method === $request->getMethod() &&
            $routePath === $requestPath;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function handle(Request $request): Response
    {
        [$controllerClass, $methodName] = $this->handler;
        $container = $request->getContainer();

        // 使用容器创建控制器实例
        $controller = new $controllerClass($container);

        // 调用控制器方法
        return $controller->$methodName($request);
    }
}
