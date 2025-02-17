<?php

namespace App\Services;

use Exception;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Action\SipShowPeerAction;
use PAMI\Message\Action\CoreShowChannelsAction;
use PAMI\Message\Action\StatusAction;
use App\Logs\Logger;

class AsteriskService
{
    private $client;
    private $options;
    private Logger $logger;

    public function __construct()
    {
        $this->logger = Logger::getInstance('asterisk-service');
        $this->options = [
            'host' => 'rtp-bridge-asterisk',
            'port' => 5038,
            'username' => 'admin',
            'secret' => 'admin123',
            'connect_timeout' => 10,
            'read_timeout' => 10
        ];
    }

    private function connect()
    {
        if (!$this->client) {
            $this->client = new ClientImpl($this->options);
            $this->client->open();
        }
    }

    /**
     * 发起 SIP 呼叫到 WebRTC 房间
     */
    public function initiateCall(string $extension, string $roomId): array
    {
        try {
            $this->connect();
            $this->logger->info('Initiating call', [
                'extension' => $extension,
                'roomId' => $roomId
            ]);

            // 检查分机是否在线
            $peerAction = new SipShowPeerAction($extension);
            $peerResponse = $this->client->send($peerAction);

            if ($peerResponse->getKeys()['Status'] !== 'OK') {
                throw new Exception("Extension $extension is not available");
            }

            // 创建呼叫动作
            $channel = 'SIP/' . $extension;
            $action = new OriginateAction($channel);
            $action->setContext('default');
            $action->setExtension('9' . $roomId); // 使用9前缀表示转发到Janus
            $action->setPriority('1');
            $action->setAsync(true);
            $action->setCallerId('WebRTC-Room-' . $roomId);

            // 设置 SIP 头
            $action->setVariable('__SIPADDHEADER01', 'X-Room-Number: ' . $roomId);
            $action->setVariable('__SIPADDHEADER02', 'X-Janus-Room: ' . $roomId);
            $action->setVariable('__SIPADDHEADER03', 'X-Source: asterisk');
            $action->setVariable('__SIPADDHEADER04', 'X-Conference-Room: ' . $roomId);
            $action->setVariable('__SIPADDHEADER05', 'X-Conference-Server: rtp-bridge-janus');

            $response = $this->client->send($action);
            $actionId = $response->getActionId();

            // 记录呼叫状态
            $this->logger->info('Call initiated', [
                'actionId' => $actionId,
                'extension' => $extension,
                'roomId' => $roomId,
                'response' => $response->getKeys()
            ]);

            return [
                'actionId' => $actionId,
                'extension' => $extension,
                'roomId' => $roomId,
                'status' => 'initiated',
                'response' => $response->getKeys()
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to initiate call', [
                'error' => $e->getMessage(),
                'extension' => $extension,
                'roomId' => $roomId
            ]);
            throw new Exception("Failed to initiate call: " . $e->getMessage());
        } finally {
            if ($this->client) {
                $this->client->close();
                $this->client = null;
            }
        }
    }

    /**
     * 获取呼叫状态
     */
    public function getCallStatus(string $channel): array
    {
        try {
            $this->connect();

            $action = new StatusAction($channel);
            $response = $this->client->send($action);

            return [
                'channel' => $channel,
                'status' => $response->getKeys()
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get call status', [
                'error' => $e->getMessage(),
                'channel' => $channel
            ]);
            throw new Exception("Failed to get call status: " . $e->getMessage());
        } finally {
            if ($this->client) {
                $this->client->close();
                $this->client = null;
            }
        }
    }

    /**
     * 获取活动通道列表
     */
    public function getActiveChannels(): array
    {
        try {
            $this->connect();

            $action = new CoreShowChannelsAction();
            $response = $this->client->send($action);

            return [
                'channels' => $response->getKeys()
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get active channels', [
                'error' => $e->getMessage()
            ]);
            throw new Exception("Failed to get active channels: " . $e->getMessage());
        } finally {
            if ($this->client) {
                $this->client->close();
                $this->client = null;
            }
        }
    }
}
