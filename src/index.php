<?php

declare(strict_types=1);

namespace App;

require __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Logs\Logger;
use App\Logs\LogRotateService;
use App\Providers\DatabaseServiceProvider;
use App\Server\ApiServer;
use App\Utils\Container;
use Doctrine\ORM\EntityManager;
use DI\ContainerBuilder;

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
        (int) $config->get('LOG_MAX_SIZE'),
        (int) $config->get('LOG_MAX_FILES'),
        (int) $config->get('LOG_RETENTION_DAYS')
    );

    // 执行日志轮转
    $logRotateService->rotate();
    $logger->info('Log rotation completed');

    // 初始化依赖注入容器
    $containerBuilder = new ContainerBuilder();

    // 获取EntityManager实例
    $entityManager = DatabaseServiceProvider::getEntityManager();

    // 注册核心服务
    $containerBuilder->addDefinitions([
        // 配置服务
        Config::class => $config,

        // 日志服务
        Logger::class => $logger,

        // 日志轮转服务
        LogRotateService::class => $logRotateService,

        // 数据库服务
        EntityManager::class => $entityManager,

        // Http Client
        'http_client' => fn() => new \GuzzleHttp\Client(),
    ]);

    // 构建容器
    $container = $containerBuilder->build();

    // 设置全局容器实例
    Container::setInstance($container);

    // 初始化API服务器
    $apiServer = new ApiServer();

    $apiServer->run();

    // 主应用循环
    $logger->info('Application started', [
        'environment' => $config->get('APP_ENV', 'production')
    ]);

    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () use ($logger) {
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
