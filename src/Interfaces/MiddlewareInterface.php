<?php

namespace App\Interfaces;

use App\Http\Request;
use App\Http\Response;

interface MiddlewareInterface
{
    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next 调用next()继续执行下一个中间件
     * @return void 通过调用$next()或直接返回响应来控制流程
     */
    public function handle(Request $request, Response $response, callable $next);
}
