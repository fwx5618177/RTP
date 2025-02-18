<?php

declare(strict_types=1);

namespace App\Media;

use App\Logs\Logger;
use App\Exceptions\MediaException;

class CallStateManager
{
    private Logger $logger;
    private array $calls = [];

    // 呼叫状态常量
    public const STATE_INIT = 'initialized';
    public const STATE_RINGING = 'ringing';
    public const STATE_ANSWERED = 'answered';
    public const STATE_CONNECTED = 'connected';
    public const STATE_DISCONNECTED = 'disconnected';
    public const STATE_FAILED = 'failed';

    public function __construct()
    {
        $this->logger = Logger::getInstance('call-state-manager');
    }

    /**
     * 创建新的呼叫记录
     */
    public function createCall(string $callId, array $initialData = []): void
    {
        $this->calls[$callId] = array_merge([
            'state' => self::STATE_INIT,
            'start_time' => microtime(true),
            'last_update' => microtime(true),
            'events' => [],
            'error' => null
        ], $initialData);

        $this->logStateChange($callId, self::STATE_INIT);
    }

    /**
     * 更新呼叫状态
     */
    public function updateCallState(string $callId, string $newState, array $additionalData = []): void
    {
        if (!isset($this->calls[$callId])) {
            throw new MediaException("Call not found: $callId");
        }

        $oldState = $this->calls[$callId]['state'];
        $this->calls[$callId] = array_merge($this->calls[$callId], [
            'state' => $newState,
            'last_update' => microtime(true)
        ], $additionalData);

        $this->logStateChange($callId, $newState, $oldState);
    }

    /**
     * 记录呼叫事件
     */
    public function addCallEvent(string $callId, string $event, array $data = []): void
    {
        if (!isset($this->calls[$callId])) {
            throw new MediaException("Call not found: $callId");
        }

        $this->calls[$callId]['events'][] = [
            'timestamp' => microtime(true),
            'event' => $event,
            'data' => $data
        ];

        $this->logger->info('Call event recorded', [
            'callId' => $callId,
            'event' => $event,
            'data' => $data
        ]);
    }

    /**
     * 记录呼叫错误
     */
    public function setCallError(string $callId, string $error): void
    {
        if (!isset($this->calls[$callId])) {
            throw new MediaException("Call not found: $callId");
        }

        $this->calls[$callId]['error'] = $error;
        $this->updateCallState($callId, self::STATE_FAILED, ['error' => $error]);

        $this->logger->error('Call error recorded', [
            'callId' => $callId,
            'error' => $error
        ]);
    }

    /**
     * 获取呼叫状态
     */
    public function getCallState(string $callId): ?array
    {
        return $this->calls[$callId] ?? null;
    }

    /**
     * 获取呼叫持续时间
     */
    public function getCallDuration(string $callId): float
    {
        if (!isset($this->calls[$callId])) {
            throw new MediaException("Call not found: $callId");
        }

        return microtime(true) - $this->calls[$callId]['start_time'];
    }

    /**
     * 检查呼叫是否活动
     */
    public function isCallActive(string $callId): bool
    {
        if (!isset($this->calls[$callId])) {
            return false;
        }

        $activeStates = [self::STATE_RINGING, self::STATE_ANSWERED, self::STATE_CONNECTED];
        return in_array($this->calls[$callId]['state'], $activeStates);
    }

    /**
     * 获取所有活动呼叫
     */
    public function getActiveCalls(): array
    {
        return array_filter($this->calls, function ($call) {
            return $this->isCallActive($call['callId']);
        });
    }

    /**
     * 清理已完成的呼叫记录
     */
    public function cleanup(): void
    {
        $now = microtime(true);
        foreach ($this->calls as $callId => $call) {
            if ($call['state'] === self::STATE_DISCONNECTED || $call['state'] === self::STATE_FAILED) {
                if ($now - $call['last_update'] > 3600) { // 清理1小时前的数据
                    unset($this->calls[$callId]);
                }
            }
        }
    }

    /**
     * 记录状态变更
     */
    private function logStateChange(string $callId, string $newState, ?string $oldState = null): void
    {
        $this->logger->info('Call state changed', [
            'callId' => $callId,
            'oldState' => $oldState,
            'newState' => $newState,
            'duration' => $this->getCallDuration($callId)
        ]);
    }
}
