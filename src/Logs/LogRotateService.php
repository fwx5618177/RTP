<?php

namespace App\Logs;

use DateTime;
use Exception;
use App\Exceptions\LogRotateException;
use App\Exceptions\MailNotificationException;

class LogRotateService
{
    private string $logDir;
    private int $maxFiles;
    private string $logFile;
    private int $maxSize;
    private int $retentionDays;
    private array $mailConfig;

    public function __construct(
        string $logDir,
        string $logFile,
        int $maxSize = 10485760,
        int $maxFiles = 10,
        int $retentionDays = 30,
        array $mailConfig = []
    )
    {
        $this->logDir = rtrim($logDir, '/');
        $this->logFile = $logFile;
        $this->maxSize = $maxSize;
        $this->maxFiles = $maxFiles;

        if (!is_dir($this->logDir)) {
            if (mkdir($this->logDir, 0755, true)) {
                $logger = Logger::getInstance('logrotate');
                $logger->info("Created log directory: {$this->logDir}");
            } else {
                $logger = Logger::getInstance('logrotate');
                $logger->error("Failed to create log directory: {$this->logDir}");
                throw new LogRotateException("Failed to create log directory: {$this->logDir}");
            }
        }
    }

    public function rotate(): void
    {
        $logPath = "{$this->logDir}/{$this->logFile}";

        if (!file_exists($logPath) || filesize($logPath) < $this->maxSize) {
            return;
        }

        $this->archiveCurrentLog($logPath);
        $this->cleanupOldArchives();
    }

    private function archiveCurrentLog(string $logPath): void
    {
        $timestamp = (new DateTime())->format('Ymd_His');
        $archivePath = "{$this->logDir}/{$this->logFile}.{$timestamp}.log";

        if (!rename($logPath, $archivePath)) {
            $logger = Logger::getInstance('logrotate');
            $logger->error("Failed to rotate log file: {$logPath}");
            throw new LogRotateException("Failed to rotate log file: {$logPath}");
        }

        // 设置归档日志文件权限
        chmod($archivePath, 0640);

        // 压缩归档日志
        $this->compressLogFile($archivePath);
    }

    private function compressLogFile(string $filePath): void
    {
        if (!extension_loaded('zlib')) {
            return;
        }

        $compressedPath = "{$filePath}.gz";
        $data = file_get_contents($filePath);
        $compressed = gzencode($data, 9);

        if (file_put_contents($compressedPath, $compressed)) {
            $logger = Logger::getInstance('logrotate');
            $logger->info("Successfully compressed log file: {$filePath}");
            unlink($filePath);
        } else {
            $logger = Logger::getInstance('logrotate');
            $logger->error("Failed to compress log file: {$filePath}");
            throw new LogRotateException("Failed to compress log file: {$filePath}");
        }
    }

    private function cleanupOldArchives(): void
    {
        $pattern = "{$this->logDir}/{$this->logFile}.*.log*";
        $files = glob($pattern);

        // 按时间清理
        $now = time();
        $files = array_filter($files, function($file) use ($now) {
            return ($now - filemtime($file)) < ($this->retentionDays * 86400);
        });

        // 按数量清理
        if (count($files) > $this->maxFiles) {
            usort($files, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            $filesToDelete = array_slice($files, 0, count($files) - $this->maxFiles);
            foreach ($filesToDelete as $file) {
                if (unlink($file)) {
                    $logger = Logger::getInstance('logrotate');
                    $logger->info("Deleted old log file: {$file}");
                } else {
                    $logger = Logger::getInstance('logrotate');
                    $logger->error("Failed to delete old log file: {$file}");
                }
            }
        }
    }

    public function getLogFiles(): array
    {
        $pattern = "{$this->logDir}/{$this->logFile}*.log*";
        return glob($pattern) ?: [];
    }

    public function setupSystemLogrotate(): void
    {
        $config = <<<EOL
{$this->logDir}/{$this->logFile} {
    daily
    missingok
    rotate {$this->maxFiles}
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        /usr/bin/systemctl reload php-fpm > /dev/null 2>&1 || true
    endscript
}
EOL;

        $configPath = "/etc/logrotate.d/{$this->logFile}";
        if (file_put_contents($configPath, $config)) {
            chmod($configPath, 0644);
        } else {
            $logger = Logger::getInstance('logrotate');
            $logger->error("Failed to write logrotate config");
            throw new LogRotateException("Failed to write logrotate config");
        }
    }

    public function notify(string $message): void
    {
        $logger = Logger::getInstance('logrotate');
        
        // 记录日志
        $logger->logWithColor('info', $message);
        
        // 发送系统通知
        if (function_exists('syslog')) {
            syslog(LOG_NOTICE, $message);
        }

        // 发送邮件通知
        if (!empty($this->mailConfig)) {
            $this->sendMailNotification($message);
        }
    }

    private function sendMailNotification(string $message): void
    {
        $subject = "Log Rotation Notification";
        
        // 使用内置mail函数
        if ($this->mailConfig['driver'] === 'mail' && function_exists('mail')) {
            if (!mail(
                $this->mailConfig['recipients'],
                $subject,
                $message,
                $this->buildMailHeaders()
            )) {
                $logger = Logger::getInstance('logrotate');
                $logger->error("Failed to send mail notification");
                throw new MailNotificationException("Failed to send mail notification");
            }
            $logger = Logger::getInstance('logrotate');
            $logger->info("Mail notification sent successfully");
            return;
        }

        // 使用SMTP
        if ($this->mailConfig['driver'] === 'smtp') {
            $transport = (new \Swift_SmtpTransport(
                $this->mailConfig['host'],
                $this->mailConfig['port']
            ))
            ->setUsername($this->mailConfig['username'])
            ->setPassword($this->mailConfig['password']);

            $mailer = new \Swift_Mailer($transport);
            $swiftMessage = (new \Swift_Message($subject))
                ->setFrom([$this->mailConfig['from']])
                ->setTo($this->mailConfig['recipients'])
                ->setBody($message);

            try {
                $mailer->send($swiftMessage);
                $logger = Logger::getInstance('logrotate');
                $logger->info("Mail notification sent successfully via SMTP");
                return;
            } catch (\Exception $e) {
                $logger = Logger::getInstance('logrotate');
                $logger->error("Failed to send mail notification via SMTP: " . $e->getMessage());
                throw new MailNotificationException("Failed to send mail notification via SMTP: " . $e->getMessage());
            }
        }

        // 使用第三方服务
        if ($this->mailConfig['driver'] === 'ses' && class_exists('Aws\Ses\SesClient')) {
            $client = new \Aws\Ses\SesClient([
                'version' => 'latest',
                'region'  => $this->mailConfig['region'],
                'credentials' => [
                    'key'    => $this->mailConfig['key'],
                    'secret' => $this->mailConfig['secret'],
                ],
            ]);

            $logger = Logger::getInstance('logrotate');
            $logger->info("Mail notification sent successfully via SES");

            $client->sendEmail([
                'Destination' => [
                    'ToAddresses' => $this->mailConfig['recipients'],
                ],
                'Message' => [
                    'Body' => [
                        'Text' => [
                            'Charset' => 'UTF-8',
                            'Data' => $message,
                        ],
                    ],
                    'Subject' => [
                        'Charset' => 'UTF-8',
                        'Data' => $subject,
                    ],
                ],
                'Source' => $this->mailConfig['from'],
            ]);
        }
    }

    private function buildMailHeaders(): string
    {
        return implode("\r\n", [
            'From: ' . $this->mailConfig['from'],
            'Reply-To: ' . $this->mailConfig['from'],
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-type: text/plain; charset=utf-8'
        ]);
    }
}
