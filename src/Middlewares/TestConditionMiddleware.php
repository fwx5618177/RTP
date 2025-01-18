<?php

namespace App\Middlewares;

use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;

class TestConditionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): Response
    {
        $logger = Logger::getInstance('middleware');
        
        // 检查是否需要截断请求
        if ($request->getHeader('x-stop-here') === 'true') {
            $logger->info('TestConditionMiddleware: Request stopped');
            return $response
                ->setHeader('x-stopped-by', 'condition-middleware')
                ->setStatusCode(403)
                ->setBody([
                    'message' => 'Request stopped by condition middleware',
                    'time' => time()
                ]);
        }

        // 检查是否需要跳过此中间件
        if ($request->getHeader('x-skip-condition') === 'true') {
            $logger->info('TestConditionMiddleware: Skipping middleware');
            return $next($request, $response);
        }

        // 正常处理流程
        $logger->info('TestConditionMiddleware: Normal flow');
        $response->header('x-condition-passed', 'true');
        
        return $next($request, $response);
    }
} 