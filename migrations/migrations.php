<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\Tools\Console\ConsoleRunner;

$config = new PhpFile('migrations.php');

$conn = DriverManager::getConnection([
    'driver' => 'pdo_mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname' => $_ENV['DB_NAME'] ?? 'rtp_bridge',
    'user' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? 'password',
]);

return DependencyFactory::fromConnection($config, new ExistingConnection($conn));
