<?php

namespace App\Logs;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;

class Logger extends MonologLogger
{
    private static array $instances = [];

    public static function getInstance(string $name = 'app', string $logDir = null, int $level = MonologLogger::DEBUG): self
    {
        if (!isset(self::$instances[$name])) {
            $logger = new self($name);
            
            // 默认日志目录为项目根目录下的 logs 目录
            $logFile = $logDir ? rtrim($logDir, '/').'/'.$name.'.log' : __DIR__.'/../../logs/'.$name.'.log';
            
            $stream = new StreamHandler($logFile, $level);
            
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s',
                true,
                true
            );
            
            $stream->setFormatter($formatter);
            
            $logger->pushHandler($stream);
            $logger->pushProcessor(new IntrospectionProcessor());
            $logger->pushProcessor(new MemoryUsageProcessor());
            
            self::$instances[$name] = $logger;
        }

        return self::$instances[$name];
    }

    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    public function debug(string $message, array $context = [], bool $withColor = true): void
    {
        $this->logWithColor('debug', $message, $context, $withColor);
    }

    public function info(string $message, array $context = [], bool $withColor = true): void
    {
        $this->logWithColor('info', $message, $context, $withColor);
    }

    public function warning(string $message, array $context = [], bool $withColor = true): void
    {
        $this->logWithColor('warning', $message, $context, $withColor);
    }

    public function error(string $message, array $context = [], bool $withColor = true): void
    {
        $this->logWithColor('error', $message, $context, $withColor);
    }

    public function critical(string $message, array $context = [], bool $withColor = true): void
    {
        $this->logWithColor('critical', $message, $context, $withColor);
    }

    public function alert(string $message, array $context = [], bool $withColor = true): void
    {
        $this->logWithColor('alert', $message, $context, $withColor);
    }

    public function emergency(string $message, array $context = [], bool $withColor = true): void
    {
        $this->logWithColor('emergency', $message, $context, $withColor);
    }

    private function logWithColor(string $level, string $message, array $context = [], bool $withColor = true): void
    {
        if ($withColor) {
            $colors = [
                'debug' => "\033[36m", // cyan
                'info' => "\033[32m",  // green
                'notice' => "\033[34m", // blue
                'warning' => "\033[33m", // yellow
                'error' => "\033[31m", // red
                'critical' => "\033[35m", // magenta
                'alert' => "\033[41m", // red background
                'emergency' => "\033[41m", // red background
            ];

            $color = $colors[$level] ?? "\033[0m";
            $reset = "\033[0m";
            $message = $color.$message.$reset;
        }

        $this->log($level, $message, $context);
    }
}
