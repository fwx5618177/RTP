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

    public function run($host, $port): void
    {
        $this->logger->info("Starting Swoole HTTP server on http://{$host}:{$port}");

        $http = new \Swoole\Http\Server($host, $port);

        $http->on('start', function ($server) use ($host, $port) {
            $this->logger->info("Swoole HTTP server started at http://{$host}:{$port}");
        });

        $http->on('request', function (\Swoole\Http\Request $swooleRequest, \Swoole\Http\Response $swooleResponse) {
            try {
                // 将Swoole请求转换为应用Request对象
                $request = Request::createFromSwoole($swooleRequest);
                $request->setContainer(Container::getInstance());

                // 记录请求开始
                $this->logger->debug('Request received', [
                    'method' => $request->getMethod(),
                    'path' => $request->getPath(),
                    'remote_address' => $swooleRequest->server['remote_addr']
                ]);

                // 路由匹配
                $route = $this->router->match($request);

                // 执行路由处理器（包含中间件）
                $handlerResponse = $route->handle($request);

                // 记录请求处理完成
                $this->logger->info('Request handled', [
                    'method' => $request->getMethod(),
                    'path' => $request->getPath(),
                    'status_code' => $handlerResponse->getStatusCode()
                ]);

                // 设置响应头
                foreach ($handlerResponse->getHeaders() as $name => $value) {
                    $swooleResponse->header($name, $value);
                }

                // 设置状态码和响应内容
                $swooleResponse->status($handlerResponse->getStatusCode());
                $swooleResponse->end($handlerResponse->getBody());
            } catch (\Exception $e) {
                // 错误处理
                $statusCode = is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
                $errorResponse = new Response([
                    'error' => $e->getMessage(),
                    'code' => $statusCode
                ], $statusCode);

                $swooleResponse->status($statusCode);
                $swooleResponse->end($errorResponse->getBody());
            }
        });

        $http->start();
    }
}
