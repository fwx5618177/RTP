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
            return; // 不调用next()，直接返回
        }

        // 测试next调用
        if ($request->getHeader('X-Test-Next') === 'true') {
            $logger->info('TestSkipMiddleware: Not Calling next middleware');
            return $response->header('X-Test-Next', 'passed');
        }

        // 默认继续执行下一个中间件
        $next();
    }
}
