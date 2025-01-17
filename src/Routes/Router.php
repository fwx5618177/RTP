<?php

namespace App\Routes;

use App\Http\Request;
use App\Http\Response;
use App\Routes\Route;
use App\Exceptions\RouteNotFoundException;

class Router
{
    private array $routes = [];

    public function addRoute(string $method, string $path, array $controllerCallable, array $middleware = []): Route
    {
        [$controllerClass, $methodName] = $controllerCallable;
        $route = new Route($method, $path, [$controllerClass, $methodName], $middleware);
        $this->routes[] = $route;
        return $route;
    }

    public function loadRoutes(array $routes): void
    {
        foreach ($routes as $route) {
            $this->addRoute(
                $route['method'],
                $route['path'],
                $route['handler'],
                $route['middleware']
            );
        }
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
            return $route->handle($request);
        } catch (RouteNotFoundException $e) {
            return (new Response())
                ->status(404)
                ->body(['error' => $e->getMessage()]);
        }
    }
}
