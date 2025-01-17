<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Logs\Logger;
use App\Logs\LogRotateService;

try {
    // Initialize configuration
    $config = Config::getInstance();
    $getConfig = $config->getLogConfig();

    // Initialize logger
    $logDir = dirname($getConfig['path']);
    $logger = Logger::getInstance('app', $logConfig['path']);
    
    // Initialize log rotation service
    $logRotateService = new LogRotateService(
        $logDir,
        basename($logConfig['path']),
        $logConfig['max_size'],
        $logConfig['max_files'],
        $logConfig['retention_days']
    );

    // Log application start
    $logger->info('Application started', [
        'environment' => $config->get('app.env', 'production'),
        'log_level' => $logConfig['level']
    ]);

    // Perform log rotation
    $logRotateService->rotate();
    $logger->info('Log rotation completed');

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
    
    exit(1);
}
