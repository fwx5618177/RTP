<?php

declare(strict_types=1);

namespace App\Logs;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;

class Logger extends MonologLogger
{
    private static array $instances = [];

    public static function getInstance(string $name = 'app', ?string $logDir = null, int $level = MonologLogger::DEBUG): self
    {
        if (! isset(self::$instances[$name])) {
            $logger = new self($name);
            // 默认日志目录为项目根目录下的 logs 目录
            $logFile = $logDir ? __DIR__.'/../../'.rtrim($logDir, '/').'/'.$name.'.log' : __DIR__.'/../../logs/'.$name.'.log';

            // 文件日志handler
            $fileStream = new StreamHandler($logFile, $level);

            $formatter = new LineFormatter(
                "\033[36m[%datetime%]\033[0m \033[1m%level_name%\033[0m > %message% %context%\n",
                'Y-m-d H:i:s',
                true,
                true
            );

            $fileStream->setFormatter($formatter);
            $logger->pushHandler($fileStream);

            // 终端输出handler
            $consoleStream = new StreamHandler('php://stdout', $level);
            $consoleStream->setFormatter($formatter);
            $logger->pushHandler($consoleStream);
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

    public function debug($message, array $context = []): void
    {
        $this->logWithColor('debug', $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->logWithColor('info', $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->logWithColor('warning', $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->logWithColor('error', $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->logWithColor('critical', $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->logWithColor('alert', $message, $context);
    }

    public function emergency($message, array $context = []): void
    {
        $this->logWithColor('emergency', $message, $context);
    }

    private function logWithColor(string $level, $message, array $context = []): void
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

        $color = $colors[$level] ?? "\033[0m";
        $reset = "\033[0m";
        $message = $color.$message.$reset;
        $this->log($level, $message, $context);
    }
}
