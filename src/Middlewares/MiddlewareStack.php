<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;

class MiddlewareStack
{
    private array $middlewares;

    public function __construct(array $middlewares = [])
    {
        $this->middlewares = $middlewares;
    }

    public function handle(Request $request): ?Response
    {
        if (empty($this->middlewares)) {
            return null;
        }

        $middleware = array_shift($this->middlewares);
        return $middleware->handle($request, function (Request $request) {
            return $this->handle($request);
        });
    }
}
