<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Logs\Logger;
use App\Logs\LogRotateService;

try {
    // 初始化 Config 实例
    $config = Config::getInstance();
    $config->setLogger(Logger::getInstance('config'));

    // 初始化日志系统
    $logDir = $config->get('LOG_DIR');
    $logger = Logger::getInstance('app', $logDir);
    
    // Initialize log rotation service
    $logRotateService = new LogRotateService(
        $logDir,
        $config->get('LOG_ROTATE_FILE'),
        $config->get('LOG_MAX_SIZE'),
        $config->get('LOG_MAX_FILES'),
        $config->get('LOG_RETENTION_DAYS'),
    );

    // Log application start
    $logger->info('Application started', [
        'environment' => $config->get('APP_ENV', 'production')
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
