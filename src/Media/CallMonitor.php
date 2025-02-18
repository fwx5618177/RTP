<?php

declare(strict_types=1);

namespace App\Media;

use App\Config\Config;
use App\Logs\Logger;
use App\Exceptions\MediaException;

class CallMonitor
{
    private Logger $logger;
    private Config $config;
    private array $callStats = [];
    private array $qualityThresholds;
    private int $statsInterval;

    public function __construct()
    {
        $this->logger = Logger::getInstance('call-monitor');
        $this->config = Config::getInstance();
        $this->qualityThresholds = $this->config->get('janusAudioBridgeConfig.monitoring.quality_threshold');
        $this->statsInterval = $this->config->get('janusAudioBridgeConfig.monitoring.stats_interval');
    }

    /**
     * 更新呼叫统计信息
     */
    public function updateCallStats(string $callId, array $stats): void
    {
        $this->callStats[$callId] = array_merge($this->callStats[$callId] ?? [], [
            'last_update' => microtime(true),
            'stats' => $stats,
            'quality_issues' => $this->analyzeQuality($stats)
        ]);

        $this->logQualityIssues($callId);
    }

    /**
     * 分析媒体质量
     */
    private function analyzeQuality(array $stats): array
    {
        $issues = [];

        // 检查丢包率
        if (isset($stats['packet_loss']) && $stats['packet_loss'] > $this->qualityThresholds['packet_loss']) {
            $issues[] = [
                'type' => 'packet_loss',
                'value' => $stats['packet_loss'],
                'threshold' => $this->qualityThresholds['packet_loss']
            ];
        }

        // 检查抖动
        if (isset($stats['jitter']) && $stats['jitter'] > $this->qualityThresholds['jitter']) {
            $issues[] = [
                'type' => 'jitter',
                'value' => $stats['jitter'],
                'threshold' => $this->qualityThresholds['jitter']
            ];
        }

        // 检查往返时延
        if (isset($stats['rtt']) && $stats['rtt'] > $this->qualityThresholds['rtt']) {
            $issues[] = [
                'type' => 'rtt',
                'value' => $stats['rtt'],
                'threshold' => $this->qualityThresholds['rtt']
            ];
        }

        return $issues;
    }

    /**
     * 记录质量问题
     */
    private function logQualityIssues(string $callId): void
    {
        $stats = $this->callStats[$callId] ?? null;
        if (!$stats || empty($stats['quality_issues'])) {
            return;
        }

        foreach ($stats['quality_issues'] as $issue) {
            $this->logger->warning('Call quality issue detected', [
                'callId' => $callId,
                'type' => $issue['type'],
                'value' => $issue['value'],
                'threshold' => $issue['threshold']
            ]);
        }
    }

    /**
     * 获取呼叫统计信息
     */
    public function getCallStats(string $callId): ?array
    {
        return $this->callStats[$callId] ?? null;
    }

    /**
     * 获取所有活动呼叫的统计信息
     */
    public function getAllCallStats(): array
    {
        return $this->callStats;
    }

    /**
     * 清理过期的统计信息
     */
    public function cleanup(): void
    {
        $now = microtime(true);
        foreach ($this->callStats as $callId => $stats) {
            if ($now - $stats['last_update'] > 3600) { // 清理1小时前的数据
                unset($this->callStats[$callId]);
            }
        }
    }

    /**
     * 检查呼叫质量是否达标
     */
    public function isCallQualityAcceptable(string $callId): bool
    {
        $stats = $this->callStats[$callId] ?? null;
        if (!$stats) {
            return true;
        }

        return empty($stats['quality_issues']);
    }

    /**
     * 获取质量问题的详细信息
     */
    public function getQualityIssues(string $callId): array
    {
        return $this->callStats[$callId]['quality_issues'] ?? [];
    }
}
