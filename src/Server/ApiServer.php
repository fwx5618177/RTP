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
        $routes = require __DIR__ . '/../Config/routes.php';
        $routes($this->router);
        $this->logger = Container::getInstance()->get(Logger::class);
    }

    public function run($port): void
    {
        $host = '0.0.0.0';
        $this->logger->info("Starting Swoole HTTP server on http://{$host}:{$port}");

        $http = new \Swoole\Http\Server($host, $port);

        $http->on('start', function ($server) use ($host, $port) {
            $this->logger->info("Swoole HTTP server started at http://{$host}:{$port}");
        });

        $http->on('request', function (\Swoole\Http\Request $swooleRequest, \Swoole\Http\Response $swooleResponse) {
            try {
                $request = Request::createFromSwoole($swooleRequest);
                $request->setContainer(Container::getInstance());

                // 路由处理（包含中间件处理）
                $response = $this->router->handle($request);

                // 发送响应
                foreach ($response->getHeaders() as $name => $value) {
                    $swooleResponse->header($name, $value);
                }
                $swooleResponse->status($response->getStatusCode());
                $swooleResponse->end($response->getBody());
            } catch (\Exception $e) {
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
