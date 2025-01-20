<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Logs\Logger;
use App\Providers\DatabaseServiceProvider;

$logger = Logger::getInstance('redis-migration');
$config = Config::getInstance();

try {
    // 获取 Redis 连接
    $redis = DatabaseServiceProvider::getRedis();

    // 清理旧数据
    $logger->info('Cleaning old Redis data...');
    $redis->flushdb();

    // 读取并执行 Redis 初始化数据
    $logger->info('Loading Redis initialization data...');
    $initData = require __DIR__ . '/migrations/redis_init_data.php';

    $logger->debug('Redis initialization data: ' . json_encode($initData));

    foreach ($initData as $key => $data) {
        try {
            switch ($data['type'] ?? 'string') {
                case 'hash':
                    foreach ($data['value'] as $field => $value) {
                        $redis->hset($key, $field, is_array($value) ? json_encode($value) : $value);
                    }
                    if (isset($data['ttl'])) {
                        $redis->expire($key, $data['ttl']);
                    }
                    break;

                case 'set':
                    foreach ($data['value'] as $value) {
                        $redis->sadd($key, $value);
                    }
                    if (isset($data['ttl'])) {
                        $redis->expire($key, $data['ttl']);
                    }
                    break;

                case 'list':
                    foreach ($data['value'] as $value) {
                        $redis->rpush($key, is_array($value) ? json_encode($value) : $value);
                    }
                    if (isset($data['ttl'])) {
                        $redis->expire($key, $data['ttl']);
                    }
                    break;

                case 'string':
                default:
                    $value = is_array($data['value']) ? json_encode($data['value']) : $data['value'];
                    if (isset($data['ttl'])) {
                        $redis->setex($key, $data['ttl'], $value);
                    } else {
                        $redis->set($key, $value);
                    }
                    break;
            }

            $logger->info("Initialized Redis key: {$key} (type: " . ($data['type'] ?? 'string') . ")");
        } catch (\Exception $e) {
            $logger->error("Failed to initialize Redis key: {$key}", [
                'error' => $e->getMessage(),
                'type' => $data['type'] ?? 'string'
            ]);
            throw $e;
        }
    }

    $logger->info('Redis migration completed successfully');
    echo "Redis migration completed successfully\n";
} catch (\Exception $e) {
    $logger->error('Redis migration failed: ' . $e->getMessage());
    echo "Redis migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
