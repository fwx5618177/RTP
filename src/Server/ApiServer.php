<?php

declare(strict_types=1);

namespace App\Server;

use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;
use App\Routes\Router;
use App\Utils\Container;

class ApiServer
{
    private Router $router;
    private Logger $logger;

    public function __construct()
    {
        $this->router = new Router();
        $routes = require __DIR__ . '/../../config/routes.php';
        foreach ($routes as $routeGroup) {
            $this->router->loadRoutes($routeGroup);
        }

        $this->logger = Container::getInstance()->get(Logger::class);
    }

    public function run(): void
    {
        // 创建请求和响应对象
        $request = Request::createFromGlobals();
        $request->setContainer(Container::getInstance());

        try {
            // 路由匹配
            $route = $this->router->match($request);

            // 执行路由处理器（包含中间件）
            $handlerResponse = $route->handle($request);

            $this->logger->info('Request handled', [
                'method' => $request->getMethod(),
                'path' => $request->getPath(),
                'status_code' => $handlerResponse->getStatusCode()
            ]);
            // 发送响应
            $handlerResponse->send();
        } catch (\Exception $e) {
            // 错误处理
            $statusCode = is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
            $errorResponse = new Response([
                'error' => $e->getMessage(),
                'code' => $statusCode
            ], $statusCode);
            $errorResponse->send();
        }
    }
}
