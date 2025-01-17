<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;

class TestSkipMiddleware
{
    public function handle(Request $request, Response $response, callable $next)
    {
        $logger = Logger::getInstance('middleware');

        $logger->info('TestSkipMiddleware: Processing request');

        // 测试截断请求
        if ($request->getHeader('X-Test-cutdown') === 'true') {
            $logger->info('TestSkipMiddleware: Cutting down request');
            return $response->header('X-Test-cutdown', 'passed')->body([
                'message' => 'Request was cut down',
            ]);
        }

        // 默认继续执行下一个中间件
        $next();
    }
}
