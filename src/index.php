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
    $config->setLogger($logger);

    // 记录启动信息
    $logger->info('Initializing application...', [
        'environment' => $config->get('APP_ENV'),
        'log_dir' => $logDir
    ]);

    // 初始化日志轮转服务
    $logRotateService = new LogRotateService(
        $logDir,
        $config->get('LOG_ROTATE_FILE'),
        (int)$config->get('LOG_MAX_SIZE'),
        (int)$config->get('LOG_MAX_FILES'),
        (int)$config->get('LOG_RETENTION_DAYS')
    );

    // 执行日志轮转
    $logRotateService->rotate();
    $logger->info('Log rotation completed');

    // 主应用循环
    $logger->info('Application started', [
        'environment' => $config->get('APP_ENV', 'production')
    ]);

    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function() use ($logger) {
        $logger->info("\n🛑 Received shutdown signal");
        $logger->info('✅ Application stopped gracefully');
        exit(0);
    });
} catch (\Throwable $e) {
    // 初始化失败处理
    if (isset($logger)) {
        $logger->emergency('Application failed to start', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    } else {
        error_log('CRITICAL: ' . $e->getMessage());
    }
    
    exit(1);
}
