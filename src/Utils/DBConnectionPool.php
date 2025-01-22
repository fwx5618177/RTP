<?php

namespace App\Utils;

use App\Config\Config;
use App\Exceptions\DatabaseException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class DBConnectionPool
{
    private static ?self $instance = null;
    /** @var array<string, Connection> */
    private array $connections = [];
    /** @var array<string, bool> */
    private array $inUse = [];
    /** @var array<string, int> */
    private array $lastUsedTime = [];

    private array $config;
    private int $maxConnections;
    private int $minConnections;
    private int $connectionTimeout;
    private int $maxRetries = 3;

    private function __construct()
    {
        $config = Config::getInstance();
        $this->maxConnections = (int)$config->get('DB_MAX_CONNECTIONS', 10);
        $this->minConnections = (int)$config->get('DB_MIN_CONNECTIONS', 2);
        $this->connectionTimeout = (int)$config->get('DB_CONNECTION_TIMEOUT', 30);

        $this->config = [
            'driver' => 'pdo_mysql',
            'host' => $config->get('DB_HOST'),
            'port' => $config->get('DB_PORT'),
            'dbname' => $config->get('DB_NAME'),
            'user' => $config->get('DB_USER'),
            'password' => $config->get('DB_PASS'),
            'charset' => 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_TIMEOUT => 5,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                \PDO::ATTR_PERSISTENT => false,
                \PDO::ATTR_AUTOCOMMIT => true,
                \PDO::MYSQL_ATTR_FOUND_ROWS => true,
                \PDO::MYSQL_ATTR_LOCAL_INFILE => false,
            ]
        ];

        $this->initializePool();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function initializePool(): void
    {
        for ($i = 0; $i < $this->minConnections; $i++) {
            $this->addConnection();
        }
    }

    private function addConnection(): string
    {
        try {
            $connection = DriverManager::getConnection($this->config);
            $id = uniqid('conn_', true);
            $this->connections[$id] = $connection;
            $this->inUse[$id] = false;
            $this->lastUsedTime[$id] = time();
            return $id;
        } catch (\Exception $e) {
            throw new DatabaseException("Failed to create database connection: " . $e->getMessage());
        }
    }

    public function getConnection(): Connection
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $connection = $this->tryGetConnection();
                if ($this->ensureConnectionActive($connection)) {
                    return $connection;
                }
            } catch (\Exception $e) {
                $lastException = $e;
            }
            $attempts++;
            if ($attempts < $this->maxRetries) {
                usleep(100000 * $attempts);
            }
        }

        throw new DatabaseException(
            "Failed to get valid database connection after {$this->maxRetries} attempts: " .
                ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    private function ensureConnectionActive(Connection $connection): bool
    {
        try {
            if (!$this->isConnectionValid($connection)) {
                $params = $connection->getParams();
                $driverOptions = $params['driverOptions'] ?? [];
                $newPdo = new \PDO(
                    sprintf(
                        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                        $params['host'],
                        $params['port'] ?? 3306,
                        $params['dbname']
                    ),
                    $params['user'],
                    $params['password'],
                    $driverOptions
                );

                $connReflection = new \ReflectionObject($connection);
                $pdoProperty = $connReflection->getProperty('_conn');
                $pdoProperty->setAccessible(true);
                $pdoProperty->setValue($connection, $newPdo);

                return true;
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function tryGetConnection(): Connection
    {
        $this->removeInactiveConnections();

        foreach ($this->inUse as $id => $used) {
            if (!$used) {
                $this->inUse[$id] = true;
                $this->lastUsedTime[$id] = time();
                return $this->connections[$id];
            }
        }

        if (count($this->connections) < $this->maxConnections) {
            $id = $this->addConnection();
            $this->inUse[$id] = true;
            return $this->connections[$id];
        }

        throw new DatabaseException("Connection pool is full");
    }

    private function isConnectionValid(Connection $connection): bool
    {
        try {
            $connection->executeQuery('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function releaseConnection(Connection $connection): void
    {
        foreach ($this->connections as $id => $conn) {
            if ($conn === $connection) {
                $this->inUse[$id] = false;
                $this->lastUsedTime[$id] = time();
                break;
            }
        }
    }

    private function removeInactiveConnections(): void
    {
        $now = time();
        foreach ($this->lastUsedTime as $id => $time) {
            if (
                !$this->inUse[$id] &&
                ($now - $time > $this->connectionTimeout) &&
                count($this->connections) > $this->minConnections
            ) {

                if (isset($this->connections[$id])) {
                    try {
                        $this->connections[$id]->close();
                    } catch (\Exception $e) {
                        // 忽略关闭错误
                    }
                }
                unset($this->connections[$id]);
                unset($this->inUse[$id]);
                unset($this->lastUsedTime[$id]);
            }
        }
    }

    public function __destruct()
    {
        foreach ($this->connections as $connection) {
            try {
                if ($connection instanceof Connection) {
                    $connection->close();
                }
            } catch (\Exception $e) {
                // 忽略关闭时的错误
            }
        }
    }
}
