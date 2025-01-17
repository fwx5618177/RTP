<?php

namespace App\Routes;

use App\Http\Request;
use App\Routes\Route;
use App\Exceptions\RouteNotFoundException;

class Router
{
    private array $routes = [];

    public function addRoute(string $method, string $path, callable $handler, array $middleware = []): Route
    {
        $route = new Route($method, $path, $handler, $middleware);
        $this->routes[] = $route;
        return $route;
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
}
