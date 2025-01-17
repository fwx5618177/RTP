<?php

namespace App\Config;

use App\Exceptions\ConfigException;
use App\Logs\Logger;
use Dotenv\Dotenv;

class Config
{
    private static ?self $instance = null;
    private string $envPath;
    private ?Logger $logger = null;

    private function __construct(string $envPath)
    {
        $this->envPath = $envPath;
        $this->loadEnv();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(__DIR__.'/../../.env');
        }
        return self::$instance;
    }

    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    private function loadEnv(): void
    {
        if (!file_exists($this->envPath)) {
            $message = '.env file not found at '.$this->envPath;
            if ($this->logger) {
                $this->logger->error($message);
            }
            throw new ConfigException($message);
        }

        $dotenv = Dotenv::createImmutable(dirname($this->envPath));
        $dotenv->load();

        if ($this->logger) {
            $this->logger->info('Loaded .env file', ['path' => $this->envPath]);
        }
    }

    public function get(string $key, $default = null)
    {
        $value = $_ENV[strtoupper($key)] ?? $default;
        
        if ($value === null) {
            $message = "Config key '{$key}' not found";
            if ($this->logger) {
                $this->logger->error($message);
            }
            throw new ConfigException($message);
        }

        if ($this->logger) {
            $this->logger->debug('Config value retrieved', [
                'key' => $key,
                'value' => $value
            ]);
        }

        return $value;
    }

    public function has(string $key): bool
    {
        $exists = isset($_ENV[strtoupper($key)]);
        
        if ($this->logger) {
            $this->logger->debug('Config key check', [
                'key' => $key,
                'exists' => $exists
            ]);
        }

        return $exists;
    }

    public static function clearCache(): void
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }
    }
}
