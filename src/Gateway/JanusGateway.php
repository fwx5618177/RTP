<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Config\Config;
use App\Exceptions\GatewayException;
use App\Logs\Logger;
use RTCKit\SIP\Message;
use RTCKit\SIP\Request;
use RTCKit\SIP\Response;

class JanusGateway
{
    private Logger $logger;
    private Config $config;
    private $socket;

    public function __construct()
    {
        $this->logger = Logger::getInstance('janus-gateway');
        $this->config = Config::getInstance();

        // 创建 UDP socket 用于 SIP 通信
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$this->socket) {
            throw new GatewayException('Failed to create socket');
        }
    }

    /**
     * 创建房间会话
     */
    public function createRoomSession(string $roomName, string $userId, string $sdpOffer): array
    {
        try {
            // 1. 创建 SIP INVITE 请求
            $invite = new Request();
            $invite->method = 'INVITE';
            $invite->uri = "sip:room_{$roomName}@{$this->config->get('JANUS_HOST')}";
            $invite->extraHeaders = [
                'Via' => ['SIP/2.0/UDP ' . $this->config->get('JANUS_HOST')],
                'From' => ["<sip:{$userId}@{$this->config->get('JANUS_HOST')}>"],
                'To' => ["<sip:room_{$roomName}@{$this->config->get('JANUS_HOST')}>"],
                'Call-ID' => [uniqid()],
                'CSeq' => ['1 INVITE'],
                'Content-Type' => ['application/sdp'],
                'Content-Length' => [strlen($sdpOffer)]
            ];
            $invite->body = $sdpOffer;

            // 2. 发送 INVITE 请求
            try {
                $this->sendRequest($invite);
            } catch (\Exception $e) {
                throw new GatewayException('Failed to send SIP INVITE: ' . $e->getMessage());
            }

            // 3. 等待 SIP 响应
            $response = $this->waitForResponse();
            if (!$response) {
                throw new GatewayException('No response received from Janus Gateway');
            }

            if ($response->code !== 200) {
                throw new GatewayException(sprintf(
                    'Invalid SIP response: %d %s',
                    $response->code,
                    $response->reason ?? 'Unknown error'
                ));
            }

            if (empty($response->body)) {
                throw new GatewayException('Empty SDP answer in response');
            }

            // 4. 解析 SDP Answer
            return $this->parseSdpAnswer($response->body);
        } catch (GatewayException $e) {
            $this->logger->error('Gateway error', [
                'error' => $e->getMessage(),
                'roomName' => $roomName,
                'userId' => $userId
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new GatewayException('Failed to create room session: ' . $e->getMessage());
        }
    }

    private function sendRequest(Request $request): void
    {
        $message = $request->render();
        socket_sendto(
            $this->socket,
            $message,
            strlen($message),
            0,
            $this->config->get('JANUS_HOST'),
            5060
        );
    }

    private function waitForResponse(): ?Response
    {
        $buf = '';
        $from = '';
        $port = 0;

        socket_recvfrom($this->socket, $buf, 65535, 0, $from, $port);

        if (!empty($buf)) {
            return Response::parse($buf);
        }

        return null;
    }

    /**
     * 解析 SDP Answer
     */
    private function parseSdpAnswer(string $sdpAnswer): array
    {
        $mediaInfo = [];
        $lines = explode("\r\n", $sdpAnswer);
        $currentMedia = null;
        $connectionAddress = null;

        foreach ($lines as $line) {
            if (strpos($line, 'm=') === 0) {
                $parts = explode(' ', $line);
                $currentMedia = substr($parts[0], 2);
                $mediaInfo[$currentMedia] = [
                    'port' => (int)$parts[1],
                    'protocol' => $parts[2],
                    'formats' => array_slice($parts, 3)
                ];
            } elseif (strpos($line, 'c=') === 0 && !isset($connectionAddress)) {
                $parts = explode(' ', $line);
                $connectionAddress = end($parts);
            }
        }

        return [
            'roomId' => uniqid('room_', true),
            'ip' => $connectionAddress ?? $this->config->get('JANUS_HOST'),
            'mediaInfo' => $mediaInfo,
            'timestamp' => time()
        ];
    }
}
