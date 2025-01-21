<?php

namespace App\Routes;

use App\Exceptions\RouteNotFoundException;
use App\Logs\Logger;
use Swoole\WebSocket\Server;

class WebSocketRouter
{
    private array $routes = [];
    private Logger $logger;

    public function __construct()
    {
        $this->logger = Logger::getInstance('websocket-router');
    }

    /**
     * 添加 WebSocket 路由
     * @param string $route 路由路径
     * @param array|callable $handler 处理器
     */
    public function addRoute(string $route, array|callable $handler): void
    {
        if (is_array($handler)) {
            $className = $handler[0];
            $methodName = $handler[1];
            $handler = function (Server $server, ...$args) use ($className, $methodName) {
                $controller = new $className();
                return $controller->$methodName($server, ...$args);
            };
        }

        $this->routes[$route] = $handler;
        $this->logger->debug("Route registered", ['route' => $route]);
    }

    /**
     * 根据路由路径获取对应的处理器
     * @param string $route 路由路径
     * @return callable 处理器
     * @throws RouteNotFoundException 当路由未找到时抛出异常
     */
    public function getHandler(string $route): callable
    {
        if (!isset($this->routes[$route])) {
            $this->logger->error("WebSocket route not found", ['route' => $route]);
            throw new RouteNotFoundException("WebSocket route not found: $route");
        }

        return $this->routes[$route];
    }

    /**
     * 检查路由是否存在
     * @param string $route 路由路径
     * @return bool
     */
    public function hasRoute(string $route): bool
    {
        return isset($this->routes[$route]);
    }

    /**
     * 获取所有注册的路由
     * @return array
     */
    public function getRoutes(): array
    {
        return array_keys($this->routes);
    }

    /**
     * 分发 WebSocket 消息到对应的处理器
     */
    public function dispatch(Server $server, string $route, ...$args): mixed
    {
        if (!isset($this->routes[$route])) {
            throw new RouteNotFoundException("Route not found: $route");
        }

        return call_user_func($this->routes[$route], $server, ...$args);
    }
}
