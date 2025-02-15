<?php

namespace App\Services;

use Exception;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Action\SipShowPeerAction;

class AsteriskService
{
    private $client;
    private $options;

    public function __construct()
    {
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

    public function initiateCall(string $extension, string $roomId): array
    {
        try {
            $this->connect();

            // 检查分机是否在线
            $peerAction = new SipShowPeerAction($extension);
            $peerResponse = $this->client->send($peerAction);

            if ($peerResponse->getKeys()['Status'] !== 'OK') {
                throw new Exception("Extension $extension is not available");
            }

            // 创建呼叫动作
            $action = new OriginateAction('SIP/' . $extension);
            $action->setCallerId('PBX-Call-' . $extension);
            $action->setContext('default');
            $action->setExtension('9' . $roomId); // 使用9前缀表示转发到Janus
            $action->setPriority('1');
            $action->setAsync(true);
            $action->setVariable('SIPADDHEADER', 'X-Janus-Room: ' . $roomId);
            $action->setVariable('SIPADDHEADER', 'X-Source: asterisk');

            $response = $this->client->send($action);

            return [
                'actionId' => $response->getActionId(),
                'response' => $response->getKeys(),
                'extension' => $extension,
                'roomId' => $roomId
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to initiate call: " . $e->getMessage());
        } finally {
            if ($this->client) {
                $this->client->close();
                $this->client = null;
            }
        }
    }
}
