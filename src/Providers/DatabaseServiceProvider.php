<?php

namespace App\Providers;

use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\EntityManager;
use Doctrine\DBAL\Types\Type;
use Ramsey\Uuid\Doctrine\UuidType;

class DatabaseServiceProvider
{
    private static ?EntityManager $entityManager = null;

    public static function getEntityManager(): EntityManager
    {
        if (self::$entityManager === null) {
            // 注册 UUID 类型
            if (!Type::hasType('uuid')) {
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
                'charset' => 'utf8mb4'
            ]);

            self::$entityManager = new EntityManager($conn, $config);

            // 注册 UUID 类型到数据库平台
            $platform = $conn->getDatabasePlatform();
            $platform->registerDoctrineTypeMapping('uuid', 'uuid');
        }

        return self::$entityManager;
    }
}
