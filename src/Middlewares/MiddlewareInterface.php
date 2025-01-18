<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;

interface MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): Response;
} 