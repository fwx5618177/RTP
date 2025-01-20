<?php

namespace App\Providers;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Predis\Client as RedisClient;
use Ramsey\Uuid\Doctrine\UuidType;

class DatabaseServiceProvider
{
    private static ?EntityManager $entityManager = null;
    private static ?\Predis\Client $redis = null;

    public static function getEntityManager(): EntityManager
    {
        if (self::$entityManager === null) {
            // 注册 UUID 类型
            if (! Type::hasType('uuid')) {
                Type::addType('uuid', UuidType::class);
            }

            $config = ORMSetup::createAttributeMetadataConfiguration(
                paths: [__DIR__ . '/../../src'],
                isDevMode: true,
            );

            $appConfig = \App\Config\Config::getInstance();
            $dbType = $appConfig->get('DB_TYPE', 'mysql');
            $conn = \Doctrine\DBAL\DriverManager::getConnection([
                'driver' => 'pdo_' . $dbType,
                'host' => $appConfig->get('DB_HOST', 'localhost'),
                'port' => $appConfig->get('DB_PORT', $dbType === 'mysql' ? 3306 : 5432),
                'dbname' => $appConfig->get('DB_NAME', 'rtp_bridge'),
                'user' => $appConfig->get('DB_USER', 'root'),
                'password' => $appConfig->get('DB_PASS', 'password'),
                'charset' => 'utf8mb4',
            ]);

            self::$entityManager = new EntityManager($conn, $config);

            // 注册 UUID 类型到数据库平台
            $platform = $conn->getDatabasePlatform();
            $platform->registerDoctrineTypeMapping('uuid', 'uuid');
        }

        return self::$entityManager;
    }

    public static function getRedis(): RedisClient
    {
        if (self::$redis === null) {
            $appConfig = \App\Config\Config::getInstance();
            $logger = \App\Logs\Logger::getInstance('redis');

            try {
                $parameters = [
                    'scheme' => $appConfig->get('REDIS_SCHEME', 'tcp'),
                    'host' => $appConfig->get('REDIS_HOST', '127.0.0.1'),
                    'port' => (int) $appConfig->get('REDIS_PORT', 6379),
                    'database' => (int) $appConfig->get('REDIS_DATABASE', 0),
                    'read_write_timeout' => (float) $appConfig->get('REDIS_READ_WRITE_TIMEOUT', 2.0),
                    'persistent' => (bool) $appConfig->get('REDIS_PERSISTENT', true),
                ];

                $options = [
                    'prefix' => $appConfig->get('REDIS_PREFIX', 'rtp:'),
                    'exceptions' => true,
                ];

                $password = $appConfig->get('REDIS_PASSWORD');
                if ($password && $password !== 'null') {
                    $parameters['password'] = $password;
                }

                self::$redis = new RedisClient($parameters, $options);

                // Test connection
                self::$redis->ping();
                $logger->info('Redis connection established successfully');
            } catch (\Exception $e) {
                $logger->error('Redis connection error: ' . $e->getMessage());

                throw $e;
            }
        }

        return self::$redis;
    }
}
