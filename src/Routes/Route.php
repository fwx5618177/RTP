<?php

namespace App\Routes;

use App\Http\Request;
use App\Http\Response;
use App\Middlewares\MiddlewareStack;

class Route
{
    private string $method;
    private string $path;
    private $handler;
    private MiddlewareStack $middlewareStack;

    public function __construct(string $method, string $path, callable $handler, array $middleware = [])
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

    public function execute(Request $request): Response
    {
        return call_user_func($this->handler, $request);
    }

    public function getMiddlewareStack(): MiddlewareStack
    {
        return $this->middlewareStack;
    }
}
