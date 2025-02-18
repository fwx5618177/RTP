<?php

declare(strict_types=1);

namespace App\Media;

use App\Logs\Logger;

class StatisticsCollector
{
    private Logger $logger;
    private array $stats = [];
    private array $globalStats = [
        'total_calls' => 0,
        'active_calls' => 0,
        'failed_calls' => 0,
        'total_duration' => 0,
        'quality_issues' => [
            'packet_loss' => 0,
            'jitter' => 0,
            'rtt' => 0,
            'mos' => 0
        ]
    ];

    public function __construct()
    {
        $this->logger = Logger::getInstance('statistics-collector');
    }

    /**
     * 更新呼叫统计
     */
    public function updateCallStats(string $callId, array $stats): void
    {
        $timestamp = microtime(true);

        if (!isset($this->stats[$callId])) {
            $this->stats[$callId] = [
                'start_time' => $timestamp,
                'last_update' => $timestamp,
                'duration' => 0,
                'packet_stats' => [
                    'sent' => 0,
                    'received' => 0,
                    'lost' => 0
                ],
                'quality_stats' => [
                    'avg_packet_loss' => 0,
                    'avg_jitter' => 0,
                    'avg_rtt' => 0,
                    'avg_mos' => 0
                ],
                'events' => []
            ];
            $this->globalStats['total_calls']++;
            $this->globalStats['active_calls']++;
        }

        $this->updateStats($callId, $stats);
    }

    /**
     * 更新统计数据
     */
    private function updateStats(string $callId, array $stats): void
    {
        $current = &$this->stats[$callId];
        $current['last_update'] = microtime(true);
        $current['duration'] = $current['last_update'] - $current['start_time'];

        // 更新包统计
        if (isset($stats['packets'])) {
            $current['packet_stats']['sent'] += $stats['packets']['sent'] ?? 0;
            $current['packet_stats']['received'] += $stats['packets']['received'] ?? 0;
            $current['packet_stats']['lost'] += $stats['packets']['lost'] ?? 0;
        }

        // 更新质量统计
        if (isset($stats['quality'])) {
            $this->updateQualityStats($callId, $stats['quality']);
        }

        // 记录事件
        if (isset($stats['event'])) {
            $current['events'][] = [
                'timestamp' => microtime(true),
                'event' => $stats['event'],
                'data' => $stats['event_data'] ?? null
            ];
        }
    }

    /**
     * 更新质量统计
     */
    private function updateQualityStats(string $callId, array $quality): void
    {
        $current = &$this->stats[$callId]['quality_stats'];
        $samples = count($this->stats[$callId]['events']) + 1;

        // 计算移动平均值
        foreach (['packet_loss', 'jitter', 'rtt', 'mos'] as $metric) {
            if (isset($quality[$metric])) {
                $current["avg_$metric"] = (($current["avg_$metric"] * ($samples - 1)) + $quality[$metric]) / $samples;
            }
        }

        // 检查质量问题
        $this->checkQualityIssues($quality);
    }

    /**
     * 检查质量问题
     */
    private function checkQualityIssues(array $quality): void
    {
        $thresholds = [
            'packet_loss' => 5.0,
            'jitter' => 50,
            'rtt' => 300,
            'mos' => 3.5
        ];

        foreach ($thresholds as $metric => $threshold) {
            if (isset($quality[$metric])) {
                if (($metric === 'mos' && $quality[$metric] < $threshold) ||
                    ($metric !== 'mos' && $quality[$metric] > $threshold)
                ) {
                    $this->globalStats['quality_issues'][$metric]++;
                }
            }
        }
    }

    /**
     * 完成呼叫统计
     */
    public function finalizeCallStats(string $callId, string $status = 'completed'): void
    {
        if (isset($this->stats[$callId])) {
            $this->globalStats['active_calls']--;

            if ($status === 'failed') {
                $this->globalStats['failed_calls']++;
            }

            $this->globalStats['total_duration'] += $this->stats[$callId]['duration'];

            $this->logger->info('Call statistics finalized', [
                'callId' => $callId,
                'status' => $status,
                'duration' => $this->stats[$callId]['duration'],
                'quality_stats' => $this->stats[$callId]['quality_stats']
            ]);
        }
    }

    /**
     * 获取呼叫统计
     */
    public function getCallStats(string $callId): ?array
    {
        return $this->stats[$callId] ?? null;
    }

    /**
     * 获取全局统计
     */
    public function getGlobalStats(): array
    {
        return $this->globalStats;
    }

    /**
     * 获取质量问题统计
     */
    public function getQualityIssueStats(): array
    {
        return $this->globalStats['quality_issues'];
    }

    /**
     * 清理旧数据
     */
    public function cleanup(string $callId): void
    {
        if (isset($this->stats[$callId])) {
            $this->finalizeCallStats($callId);
            unset($this->stats[$callId]);
        }
    }

    /**
     * 生成统计报告
     */
    public function generateReport(): array
    {
        $activeCalls = $this->globalStats['active_calls'];
        $totalCalls = $this->globalStats['total_calls'];
        $failedCalls = $this->globalStats['failed_calls'];
        $successRate = $totalCalls > 0 ? (($totalCalls - $failedCalls) / $totalCalls) * 100 : 0;

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'active_calls' => $activeCalls,
            'total_calls' => $totalCalls,
            'failed_calls' => $failedCalls,
            'success_rate' => round($successRate, 2),
            'total_duration' => round($this->globalStats['total_duration'], 2),
            'avg_duration' => $totalCalls > 0 ? round($this->globalStats['total_duration'] / $totalCalls, 2) : 0,
            'quality_issues' => $this->globalStats['quality_issues']
        ];
    }
}
