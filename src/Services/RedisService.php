<?php

namespace App\Services;

use App\Logs\Logger;
use App\Providers\DatabaseServiceProvider;
use Predis\Client as RedisClient;

class RedisService
{
    private RedisClient $redis;
    private Logger $logger;

    public function __construct()
    {
        $this->redis = DatabaseServiceProvider::getRedis();
        $this->logger = Logger::getInstance('redis-service');
    }

    public function set(string $key, $value, ?int $ttl = null): bool
    {
        try {
            if ($ttl) {
                $this->redis->setex($key, $ttl, $this->serialize($value));
            } else {
                $this->redis->set($key, $this->serialize($value));
            }
            $this->logger->info('Successfully set key in Redis', ['key' => $key]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to set key in Redis', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function get(string $key)
    {
        try {
            $value = $this->redis->get($key);
            if ($value === null) {
                return null;
            }

            return $this->unserialize($value);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get key from Redis', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function delete(string $key): bool
    {
        try {
            $result = $this->redis->del([$key]);
            $this->logger->info('Successfully deleted key from Redis', ['key' => $key]);

            return $result > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete key from Redis', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function exists(string $key): bool
    {
        try {
            return (bool) $this->redis->exists($key);
        } catch (\Exception $e) {
            $this->logger->error('Failed to check key existence in Redis', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function serialize($data): string
    {
        return json_encode($data);
    }

    private function unserialize(string $data)
    {
        return json_decode($data, true);
    }
}
