<?php

namespace App\Server;

use App\Config\Config;
use App\Logs\Logger;
use App\Routes\WebSocketRouter;
use Swoole\WebSocket\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\WebSocket\Frame;

class WebSocketServer
{
    private Server $server;
    private WebSocketRouter $router;
    private Logger $logger;
    private Config $config;
    private string $host;
    private int $port;

    public function __construct()
    {
        $this->config = Config::getInstance();
        $this->logger = Logger::getInstance('websocket-server');
        $this->router = new WebSocketRouter();

        $this->host = $this->config->get('WS_HOST', '0.0.0.0');
        $this->port = (int)$this->config->get('WS_PORT', 9502);

        // 加载WebSocket路由配置
        $routeConfig = require __DIR__ . '/../Config/websocket.php';
        $routeConfig($this->router);

        $this->server = new Server($this->host, $this->port);
        $this->initializeServer();
    }

    private function initializeServer(): void
    {
        // 设置服务器配置
        $this->server->set([
            'worker_num' => 4,
            'max_request' => 1000,
            'max_conn' => 10000,
            'heartbeat_check_interval' => 60,
            'heartbeat_idle_time' => 120,
        ]);
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        // 加载WebSocket路由配置
        $routeConfig = require __DIR__ . '/../Config/websocket.php';
        $routeConfig($this->router);

        // 注册事件处理器
        $this->server->on('start', [$this, 'onStart']);
        $this->server->on('handshake', [$this, 'onHandshake']);
        $this->server->on('message', [$this, 'onMessage']);
        $this->server->on('close', [$this, 'onClose']);
    }

    public function onStart(Server $server): void
    {
        $this->logger->info('WebSocket Server started', [
            'host' => $server->host,
            'port' => $server->port,
            'master_pid' => $server->master_pid,
            'manager_pid' => $server->manager_pid,
            'worker_id' => $server->worker_id,
            'worker_pid' => $server->worker_pid,
        ]);

        // 设置进程名称
        if (function_exists('cli_set_process_title')) {
            cli_set_process_title('php-ws-master');
        }

        // 输出启动信息到控制台
        echo sprintf(
            "\033[32m[%s] WebSocket Server started at ws://%s:%d\033[0m\n",
            date('Y-m-d H:i:s'),
            $server->host,
            $server->port
        );
    }

    public function onHandshake(Request $request, Response $response): bool
    {
        $this->logger->info('WebSocket handshake request', [
            'headers' => $request->header,
            'path' => $request->server['request_uri'],
        ]);
        return $this->router->dispatch($this->server, '/handshake', $request, $response);
    }

    public function onMessage(Server $server, Frame $frame): void
    {
        $this->logger->info('Received message', [
            'fd' => $frame->fd,
            'data' => $frame->data,
        ]);
        $this->router->dispatch($server, '/message', $frame);
    }

    public function onClose(Server $server, int $fd): void
    {
        $this->logger->info('Connection closed', ['fd' => $fd]);
        $this->router->dispatch($server, '/close', $fd);
    }

    public function start(): void
    {
        $this->logger->info("Starting WebSocket server on ws://{$this->host}:{$this->port}");
        $this->server->start();
    }

    public function stop(): void
    {
        $this->server->stop();
    }

    public function getRouter(): WebSocketRouter
    {
        return $this->router;
    }
}
