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

        // 检查 HTTP 方法是否匹配
        if ($this->method !== $request->getMethod()) {
            return false;
        }

        // 如果路径完全相同, 直接返回 true
        if ($routePath === $requestPath) {
            return true;
        }

        // 处理动态路由参数
        $routeParts = explode('/', $routePath);
        $requestParts = explode('/', $requestPath);

        // 路径段数必须相同
        if (count($routeParts) !== count($requestParts)) {
            return false;
        }

        // 逐段比较路径
        for ($i = 0; $i < count($routeParts); $i++) {
            $routePart = $routeParts[$i];
            $requestPart = $requestParts[$i];

            // 检查是否是动态参数 {param}
            if (preg_match('/^{(.+)}$/', $routePart)) {
                continue; // 动态参数，跳过比较
            }

            // 静态路径段必须完全匹配
            if ($routePart !== $requestPart) {
                return false;
            }
        }

        return true;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function handle(Request $request): Response
    {
        [$controllerClass, $methodName] = $this->handler;
        $container = $request->getContainer();

        // 从容器中获取控制器实例
        $controller = $container->get($controllerClass);

        // 提取路由参数
        $params = $this->extractRouteParams($request);

        // 调用控制器方法，传入请求对象和路由参数
        return $controller->$methodName($request, ...$params);
    }

    private function extractRouteParams(Request $request): array
    {
        $requestPath = rtrim($request->getPath(), '/');
        $routePath = rtrim($this->path, '/');

        $routeParts = explode('/', $routePath);
        $requestParts = explode('/', $requestPath);
        $params = [];

        foreach ($routeParts as $index => $routePart) {
            if (preg_match('/^{(.+)}$/', $routePart, $matches)) {
                // 将URL中对应位置的值转换为适当的类型
                $value = $requestParts[$index];
                if (is_numeric($value)) {
                    $params[] = (int)$value;
                } else {
                    $params[] = $value;
                }
            }
        }

        return $params;
    }
}
