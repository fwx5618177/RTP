<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): Response
    {
        // 允许的源，这里设置为前端开发服务器地址
        $response->setHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type');
        $response->setHeader('Access-Control-Allow-Credentials', 'true');

        // 处理 OPTIONS 请求
        if ($request->getMethod() === 'OPTIONS') {
            return $response;
        }

        return $next($request, $response);
    }
}
