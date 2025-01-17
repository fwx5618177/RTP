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
        $host = '0.0.0.0';
        $port = 8080;

        $this->logger->info("Starting HTTP server on http://{$host}:{$port}");

        $socket = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (!$socket) {
            $this->logger->error("Failed to start server: {$errstr} ({$errno})");
            exit(1);
        }

        // 验证socket是否绑定成功
        $socketName = stream_socket_get_name($socket, false);
        $this->logger->debug("Socket created", [
            'address' => $socketName,
            'host' => $host,
            'port' => $port
        ]);

        stream_set_blocking($socket, false);

        while (true) {
            $conn = @stream_socket_accept($socket, 0);
            if ($conn === false) {
                usleep(10000); // 10ms
                continue;
            }

            stream_set_blocking($conn, false);
            $request = Request::createFromStream($conn);
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

                // 立即发送响应
                fwrite($conn, $handlerResponse->__toString());
                fflush($conn);
            } catch (\Exception $e) {
                // 错误处理
                $statusCode = is_int($e->getCode()) && $e->getCode() >= 400 ? $e->getCode() : 500;
                $errorResponse = new Response([
                    'error' => $e->getMessage(),
                    'code' => $statusCode
                ], $statusCode);
                fwrite($conn, $errorResponse->__toString());
                fflush($conn);
            }

            fclose($conn);
        }

        fclose($socket);
    }
}
