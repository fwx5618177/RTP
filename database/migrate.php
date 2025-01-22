<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Config;
use App\Logs\Logger;

$logger = Logger::getInstance('migration');
$config = Config::getInstance();

try {
    $pdo = new PDO(
        "mysql:host={$config->get('DB_HOST')};dbname={$config->get('DB_NAME')}",
        $config->get('DB_USER'),
        $config->get('DB_PASS')
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 读取并执行 SQL 文件
    $migrations = [
        'create_users_table.sql',
        'create_rooms_table.sql'  // 添加 rooms 表迁移
    ];

    foreach ($migrations as $migration) {
        $logger->info("Executing migration: {$migration}");
        $sql = file_get_contents(__DIR__ . '/migrations/' . $migration);
        $pdo->exec($sql);
        $logger->info("Successfully executed: {$migration}");
    }

    $logger->info('All migrations completed successfully');
    echo "Migration completed successfully\n";
} catch (PDOException $e) {
    $logger->error('Migration failed: ' . $e->getMessage());
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
