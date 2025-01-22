<?php

declare(strict_types=1);

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

    public function hSet(string $key, array $data): bool
    {
        try {
            $this->redis->hmset($key, $data);
            $this->logger->info('Successfully set hash in Redis', ['key' => $key]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to set hash in Redis', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function hGetAll(string $key): array
    {
        try {
            $data = $this->redis->hgetall($key);
            return $data ?: [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get hash from Redis', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function sAdd(string $key, array $members): int
    {
        try {
            $count = $this->redis->sadd($key, $members);
            $this->logger->info('Successfully added set members in Redis', [
                'key' => $key,
                'count' => $count
            ]);
            return $count;
        } catch (\Exception $e) {
            $this->logger->error('Failed to add set members in Redis', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    public function sRem(string $key, string $member): int
    {
        try {
            $count = $this->redis->srem($key, $member);
            $this->logger->info('Successfully removed set member in Redis', [
                'key' => $key,
                'member' => $member
            ]);
            return $count;
        } catch (\Exception $e) {
            $this->logger->error('Failed to remove set member in Redis', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }


    public function sMembers(string $key): array
    {
        try {
            $members = $this->redis->smembers($key);
            return $members ?: [];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get set members from Redis', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    public function sCard(string $key): int
    {
        try {
            return $this->redis->scard($key);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get set cardinality from Redis', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    public function del(array $keys): int
    {
        try {
            $count = $this->redis->del($keys);
            $this->logger->info('Successfully deleted keys from Redis', [
                'keys' => $keys,
                'count' => $count
            ]);
            return $count;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete keys from Redis', [
                'keys' => $keys,
                'error' => $e->getMessage(),
            ]);
            return 0;
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
            return $value === null ? null : $this->unserialize($value);
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

    /**
     * Check if member exists in the set
     *
     * @param string $key The key of the set
     * @param string $member The member to check
     * @return bool Returns true if member exists in the set, false otherwise
     */
    public function sIsMember(string $key, string $member): bool
    {
        try {
            $result = $this->redis->sismember($key, $member);
            return (bool) $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check set member in Redis', [
                'key' => $key,
                'member' => $member,
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
