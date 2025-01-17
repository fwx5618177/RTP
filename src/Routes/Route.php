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
        return $this->method === $request->getMethod() &&
            $this->path === $request->getPath();
    }

    public function handle(Request $request): Response
    {
        $response = $this->middlewareStack->handle($request);

        if ($response) {
            return $response;
        }

        [$controllerClass, $methodName] = $this->handler;
        $controller = new $controllerClass();
        return $controller->$methodName($request);
    }
}
