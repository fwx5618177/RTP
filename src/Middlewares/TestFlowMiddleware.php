<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;

class TestFlowMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): Response
    {
        $logger = Logger::getInstance('middleware');

        // 前置处理
        $logger->info('TestFlowMiddleware: Before next');
        $response->header('x-flow-middleware', 'before');

        // 调用下一个中间件
        $response = $next($request, $response);

        // 后置处理
        $logger->info('TestFlowMiddleware: After next');
        $response->header('x-flow-middleware', 'after');

        return $response;
    }
}
