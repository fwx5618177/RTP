<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;

class TestHeaderMiddleware
{
    public function handle(Request $request, Response $response, callable $next): Response
    {
        $logger = Logger::getInstance('middleware');

        // 添加测试header
        $response->header('X-Test-Middleware', 'passed');

        // 记录传入的header
        $headersConfig = $response->getHeader('X-Test-Middleware');
        $logger->info('TestHeaderMiddleware: Processing request', ['headers' => $headersConfig]);

        // 继续执行下一个中间件并返回响应
        return $next();
    }
}
