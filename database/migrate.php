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
    $sql = file_get_contents(__DIR__ . '/migrations/create_users_table.sql');
    $pdo->exec($sql);

    $logger->info('Migration completed successfully');
    echo "Migration completed successfully\n";
} catch (PDOException $e) {
    $logger->error('Migration failed: ' . $e->getMessage());
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
