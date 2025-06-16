<?php

use App\Config\Config;

require_once __DIR__ . '/../vendor/autoload.php';

$config = Config::getInstance();

return [
    'dbname' => $config->get('DB_NAME'),
    'user' => $config->get('DB_USER'),
    'password' => $config->get('DB_PASS'),
    'host' => $config->get('DB_HOST'),
    'driver' => 'pdo_mysql',
];
