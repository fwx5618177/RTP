<?php

declare(strict_types=1);

namespace App\Server;

use App\Http\Request;
use App\Http\Response;
use RtpBridge\Backend\Logger;
use App\Routes\Router;
use App\Middlewares\MiddlewareStack;

class ApiServer
{
    private Router $router;
    private MiddlewareStack $middlewareStack;

    public function __construct(Router $router, MiddlewareStack $middlewareStack)
    {
        $this->router = $router;
        $this->middlewareStack = $middlewareStack;
    }

    public function handle(Request $request): Response
    {
        try {
            // Apply global middleware
            $response = $this->middlewareStack->handle($request);
            
            if ($response !== null) {
                return $response;
            }

            // Find matching route
            $route = $this->router->match($request);

            // Apply route-specific middleware
            $routeMiddlewareResponse = $route->getMiddlewareStack()->handle($request);
            if ($routeMiddlewareResponse !== null) {
                return $routeMiddlewareResponse;
            }

            // Execute route handler
            return $route->execute($request);
        } catch (\Exception $e) {
            return new Response(
                $e->getCode() ?: 500,
                ['Content-Type' => 'application/json'],
                ['error' => $e->getMessage()]
            );
        }
    }
}
