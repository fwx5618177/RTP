<?php

namespace App\Routes;

use App\Exceptions\RouteNotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Middlewares\MiddlewareInterface;
use App\Middlewares\MiddlewareStack;

class Router
{
    private array $routes = [];
    private array $globalMiddlewares = [];

    public function addGlobalMiddleware($middleware): self
    {
        $this->globalMiddlewares[] = $middleware;

        return $this;
    }

    public function group(array $attributes, callable $callback): void
    {
        $middlewares = $attributes['middleware'] ?? [];
        $prefix = $attributes['prefix'] ?? '';

        $callback(new class ($this, $middlewares, $prefix) {
            private $router;
            private $middlewares;
            private $prefix;

            public function __construct($router, $middlewares, $prefix)
            {
                $this->router = $router;
                $this->middlewares = $middlewares;
                $this->prefix = $prefix;
            }

            public function add(string $method, string $uri, $handler): void
            {
                $this->router->add($method, $this->prefix . $uri, $handler, $this->middlewares);
            }
        });
    }

    public function match(Request $request): Route
    {
        foreach ($this->routes as $route) {
            if ($route->matches($request)) {
                return $route;
            }
        }

        throw new RouteNotFoundException("Route not found for {$request->getMethod()} {$request->getPath()}");
    }

    public function handle(Request $request): Response
    {
        try {
            $route = $this->match($request);
            $response = new Response();

            // 构建中间件栈
            $middlewareStack = new MiddlewareStack();

            // 添加全局中间件
            foreach ($this->globalMiddlewares as $middleware) {
                $middlewareStack->add($middleware);
            }

            // 添加路由特定的中间件
            foreach ($route->getMiddlewares() as $middleware) {
                $middlewareStack->add($middleware);
            }

            // 添加最终的控制器处理
            $middlewareStack->add(new class ($route) implements MiddlewareInterface {
                private $route;

                public function __construct($route)
                {
                    $this->route = $route;
                }

                public function process(Request $request, Response $response, callable $next): Response
                {
                    return $this->route->handle($request);
                }
            });

            return $middlewareStack->process($request, $response);
        } catch (RouteNotFoundException $e) {
            return (new Response())
                ->setStatusCode(404)
                ->setBody(['error' => $e->getMessage()]);
        }
    }

    public function add(string $method, string $path, array $handler, array $middlewares = []): Route
    {
        $route = new Route($method, $path, $handler, $middlewares);
        $this->routes[] = $route;

        return $route;
    }
}
