<?php

declare(strict_types=1);

namespace App\Media;

use App\Logs\Logger;

class MediaQualityMonitor
{
    private Logger $logger;
    private array $qualityData = [];
    private array $thresholds;

    public function __construct(array $thresholds = [])
    {
        $this->logger = Logger::getInstance('media-quality-monitor');
        $this->thresholds = array_merge([
            'packet_loss_threshold' => 5.0,  // 5% packet loss threshold
            'jitter_threshold' => 50,        // 50ms jitter threshold
            'rtt_threshold' => 300,          // 300ms RTT threshold
            'mos_threshold' => 3.5           // Minimum acceptable MOS score
        ], $thresholds);
    }

    /**
     * 更新媒体质量数据
     */
    public function updateQualityMetrics(string $callId, array $metrics): void
    {
        $timestamp = microtime(true);

        if (!isset($this->qualityData[$callId])) {
            $this->qualityData[$callId] = [
                'history' => [],
                'current' => null,
                'issues' => []
            ];
        }

        // 保存历史数据
        $this->qualityData[$callId]['history'][] = array_merge($metrics, ['timestamp' => $timestamp]);
        $this->qualityData[$callId]['current'] = $metrics;

        // 分析质量问题
        $this->analyzeQuality($callId, $metrics);
    }

    /**
     * 分析媒体质量
     */
    private function analyzeQuality(string $callId, array $metrics): void
    {
        $issues = [];

        // 检查丢包率
        if (isset($metrics['packet_loss']) && $metrics['packet_loss'] > $this->thresholds['packet_loss_threshold']) {
            $issues[] = [
                'type' => 'packet_loss',
                'value' => $metrics['packet_loss'],
                'threshold' => $this->thresholds['packet_loss_threshold']
            ];
        }

        // 检查抖动
        if (isset($metrics['jitter']) && $metrics['jitter'] > $this->thresholds['jitter_threshold']) {
            $issues[] = [
                'type' => 'jitter',
                'value' => $metrics['jitter'],
                'threshold' => $this->thresholds['jitter_threshold']
            ];
        }

        // 检查往返时延
        if (isset($metrics['rtt']) && $metrics['rtt'] > $this->thresholds['rtt_threshold']) {
            $issues[] = [
                'type' => 'rtt',
                'value' => $metrics['rtt'],
                'threshold' => $this->thresholds['rtt_threshold']
            ];
        }

        // 检查MOS分数
        if (isset($metrics['mos']) && $metrics['mos'] < $this->thresholds['mos_threshold']) {
            $issues[] = [
                'type' => 'mos',
                'value' => $metrics['mos'],
                'threshold' => $this->thresholds['mos_threshold']
            ];
        }

        if (!empty($issues)) {
            $this->qualityData[$callId]['issues'] = $issues;
            $this->logQualityIssues($callId, $issues);
        }
    }

    /**
     * 记录质量问题
     */
    private function logQualityIssues(string $callId, array $issues): void
    {
        foreach ($issues as $issue) {
            $this->logger->warning('Media quality issue detected', [
                'callId' => $callId,
                'issue_type' => $issue['type'],
                'current_value' => $issue['value'],
                'threshold' => $issue['threshold']
            ]);
        }
    }

    /**
     * 计算MOS分数
     */
    public function calculateMOS(array $metrics): float
    {
        // 基于E-Model (ITU-T G.107) 的简化版本
        $R = 93.2; // 初始R值

        // 丢包影响
        if (isset($metrics['packet_loss'])) {
            $R -= 2.5 * $metrics['packet_loss'];
        }

        // 延迟影响
        if (isset($metrics['rtt'])) {
            $delay = $metrics['rtt'] / 2; // 单向延迟
            $Id = 0.024 * $delay + 0.11 * ($delay - 177.3) * ($delay > 177.3 ? 1 : 0);
            $R -= $Id;
        }

        // 抖动影响
        if (isset($metrics['jitter'])) {
            $R -= 0.05 * $metrics['jitter'];
        }

        // R值转换为MOS
        $mos = 1 + 0.035 * $R + $R * ($R - 60) * (100 - $R) * 7e-6;
        return max(1.0, min(4.5, $mos));
    }

    /**
     * 获取当前质量数据
     */
    public function getCurrentQuality(string $callId): ?array
    {
        return $this->qualityData[$callId]['current'] ?? null;
    }

    /**
     * 获取质量历史数据
     */
    public function getQualityHistory(string $callId): array
    {
        return $this->qualityData[$callId]['history'] ?? [];
    }

    /**
     * 获取当前质量问题
     */
    public function getCurrentIssues(string $callId): array
    {
        return $this->qualityData[$callId]['issues'] ?? [];
    }

    /**
     * 检查是否存在严重质量问题
     */
    public function hasCriticalIssues(string $callId): bool
    {
        $issues = $this->getCurrentIssues($callId);
        return !empty($issues);
    }

    /**
     * 清理旧数据
     */
    public function cleanup(string $callId): void
    {
        unset($this->qualityData[$callId]);
    }
}
