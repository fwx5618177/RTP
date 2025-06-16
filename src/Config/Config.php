<?php

declare(strict_types=1);

namespace App\Config;

use App\Exceptions\ConfigException;
use App\Logs\Logger;
use Dotenv\Dotenv;

class Config
{
    private static ?self $instance = null;
    private string $envPath;
    private ?Logger $logger = null;

    /**
     * Janus 音频桥接配置
     */
    private array $janusAudioBridgeConfig = [
        // 音频编解码器配置
        'audio_codecs' => [
            ['payload' => 111, 'name' => 'opus', 'rate' => 48000, 'channels' => 2, 'fmtp' => 'minptime=10;useinbandfec=1'],
            ['payload' => 0, 'name' => 'PCMU', 'rate' => 8000, 'channels' => 1],
            ['payload' => 8, 'name' => 'PCMA', 'rate' => 8000, 'channels' => 1],
            ['payload' => 101, 'name' => 'telephone-event', 'rate' => 8000, 'channels' => 1, 'fmtp' => '0-15']
        ],

        // RTP 配置
        'rtp' => [
            'port_min' => 10000,
            'port_max' => 20000,
            'ptime' => 20,
            'max_bandwidth' => 128000,
            'buffer_size' => 65535,
            'jitter_buffer' => true,
            'jitter_buffer_size' => 50
        ],

        // 音频房间配置
        'room' => [
            'sampling_rate' => 48000,
            'spatial_audio' => false,
            'record' => false,
            'notify_joining' => true,
            'max_participants' => 100,
            'quality' => 4
        ],

        // 统计和监控配置
        'monitoring' => [
            'enabled' => true,
            'stats_interval' => 5000,  // 统计间隔(ms)
            'quality_threshold' => [
                'packet_loss' => 5.0,   // 允许的最大丢包率(%)
                'jitter' => 50,         // 允许的最大抖动(ms)
                'rtt' => 200            // 允许的最大往返时延(ms)
            ]
        ]
    ];

    private function __construct(string $envPath)
    {
        $this->envPath = $envPath;
        $this->loadEnv();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(__DIR__ . '/../../config/.env');
        }

        return self::$instance;
    }

    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }

    private function loadEnv(): void
    {
        if (! file_exists($this->envPath)) {
            $message = '.env file not found at ' . $this->envPath;
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

        return $value;
    }

    public function has(string $key): bool
    {
        $exists = isset($_ENV[strtoupper($key)]);

        if ($this->logger) {
            $this->logger->debug('Config key check', [
                'key' => $key,
                'exists' => $exists,
            ]);
        }

        return $exists;
    }

    public static function clearCache(): void
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
}
