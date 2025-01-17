<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Server\ApiServer;
use App\Routes\Router;
use App\Middlewares\MiddlewareStack;
use App\Http\Request;
use App\Http\Response;
use App\Config\Config;
use App\Logs\Logger;
use App\Logs\LogRotateService;

try {
    // Initialize configuration
    $config = Config::getInstance();
    
    // Ensure logs directory exists
    $logDir = dirname($config->getLogConfig()['path']);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Initialize logger
    $logConfig = $config->getLogConfig();
    $logRotateService = new LogRotateService(
        $logDir,
        basename($logConfig['path']),
        $logConfig['max_size'],
        $logConfig['max_files'],
        $logConfig['retention_days']
    );
    
    $logger = Logger::getInstance('app', $logConfig['path']);
    
    // Initialize dependencies
    $router = new Router();
    $middlewareStack = new MiddlewareStack();
    $apiServer = new ApiServer($router, $middlewareStack);

    // Log application start
    $logger->info('Application started', [
        'environment' => $config->get('app.env', 'production'),
        'log_level' => $logConfig['level']
    ]);

    // Register routes
    $router->addRoute('GET', '/api/users', function (Request $request) use ($logger) {
        $logger->debug('GET /api/users called');
        return new Response(200, ['Content-Type' => 'application/json'], [
            'data' => [
                ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Jane']
            ]
        ]);
    });

    $router->addRoute('POST', '/api/users', function (Request $request) use ($logger) {
        $data = $request->getBodyParams();
        $logger->info('New user created', $data);
        return new Response(201, ['Content-Type' => 'application/json'], [
            'message' => 'User created',
            'data' => $data
        ]);
    });

    // Create request from globals
    $request = new Request(
        $_SERVER['REQUEST_METHOD'],
        parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
        $_GET,
        json_decode(file_get_contents('php://input'), true) ?? [],
        getallheaders()
    );

    // Handle request
    $response = $apiServer->handle($request);
    $response->send();

} catch (\Throwable $e) {
    // Log error
    if (isset($logger)) {
        $logger->error($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    } else {
        error_log($e->getMessage());
    }

    // Return error response
    $response = new Response(500, ['Content-Type' => 'application/json'], [
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ]);
    $response->send();
}
