<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;

class TestSkipMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): Response
    {
        $logger = Logger::getInstance('middleware');
        
        // 前置检查，可以决定是否继续处理
        if ($request->getHeader('x-test-cutdown') === 'true') {
            $logger->info('TestSkipMiddleware: Cutting down request');
            return $response
                ->setHeader('x-test-cutdown', 'passed')
                ->setBody([
                    'message' => 'Request was cut down'
                ]);
        }

        // 继续处理链
        return $next($request, $response);
    }
}
