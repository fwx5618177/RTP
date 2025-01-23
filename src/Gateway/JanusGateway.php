<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Config\Config;
use App\Exceptions\GatewayException;
use App\Logs\Logger;
use RTCKit\SIP\Request;
use RTCKit\SIP\Response;
use RTCKit\SIP\URI;
use RTCKit\SIP\Header\{
    ViaHeader,
    FromHeader,
    ScalarHeader,
    CallIdHeader,
    CSeqHeader,
    SingleValueWithParamsHeader
};

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

            // 创建 URI 对象
            $uri = new URI();
            $uri->scheme = 'sip';
            $uri->host = $this->config->get('JANUS_HOST');
            $uri->user = "room_{$roomName}";
            $invite->uri = $uri;

            // 构建 From 和 To URI 字符串
            $fromUriStr = sprintf('sip:%s@%s', $userId, $this->config->get('JANUS_HOST'));
            $toUriStr = sprintf('sip:room_%s@%s', $roomName, $this->config->get('JANUS_HOST'));

            // 创建并设置 SIP 消息头
            $via = new ViaHeader();
            $via->values = [
                'SIP/2.0/UDP ' . $this->config->get('JANUS_HOST')
            ];
            $invite->via = $via;

            $from = new FromHeader();
            $from->uri = new URI($fromUriStr);
            $invite->from = $from;

            $to = new FromHeader();
            $to->uri = new URI($toUriStr);
            $invite->to = $to;

            $callId = new CallIdHeader();
            $callId->value = uniqid();
            $invite->callId = $callId;

            $cSeq = new CSeqHeader();
            $cSeq->sequence = 1;
            $cSeq->method = 'INVITE';
            $invite->cSeq = $cSeq;

            // 设置 Content-Type header
            $contentType = new SingleValueWithParamsHeader();
            $contentType->value = 'application/sdp';
            $invite->contentType = $contentType;

            // 设置 Content-Length header
            $contentLength = new ScalarHeader();
            $contentLength->value = (int)strlen($sdpOffer);
            $invite->contentLength = $contentLength;

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
