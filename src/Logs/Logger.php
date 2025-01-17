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

    public static function getInstance(string $name = 'app', string $logFile = null): self
    {
        if (!isset(self::$instances[$name])) {
            $logger = new self($name);
            
            $logFile = $logFile ?? storage_path('logs/'.$name.'.log');
            
            $stream = new StreamHandler($logFile, MonologLogger::DEBUG);
            
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

    public function logWithColor(string $level, string $message, array $context = [], string $color = null): void
    {
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

        $color = $color ?? $colors[$level] ?? "\033[0m";
        $reset = "\033[0m";

        $this->log($level, $color.$message.$reset, $context);
    }
}
