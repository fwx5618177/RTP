<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Config\Config;
use App\Exceptions\GatewayException;
use App\Logs\Logger;
use RTCKit\SIP\Message;
use RTCKit\SIP\Request;
use RTCKit\SIP\Header\ViaHeader;
use RTCKit\SIP\Header\CallIdHeader;
use RTCKit\SIP\Header\CSeqHeader;
use RTCKit\SIP\Header\FromHeader;
use RTCKit\SIP\Header\Header;
use RTCKit\SIP\URI;
use Socket;

class JanusGateway
{
    private Logger $logger;
    private Config $config;
    private ?Socket $socket = null;

    public function __construct()
    {
        $this->logger = Logger::getInstance('janus-gateway');
        $this->config = Config::getInstance();
        $this->initSocket();
    }

    private function initSocket(): void
    {
        try {
            $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($this->socket === false) {
                throw new GatewayException('Failed to create socket: ' . socket_strerror(socket_last_error()));
            }

            // 设置超时
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 5, 'usec' => 0]);
            socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => 5, 'usec' => 0]);

            $this->logger->info('SIP socket initialized successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize SIP socket', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new GatewayException('Failed to initialize SIP socket: ' . $e->getMessage());
        }
    }

    public function createRoomSession(string $roomName, string $userId): array
    {
        $this->logger->info('Starting room session creation', [
            'roomName' => $roomName,
            'userId' => $userId
        ]);

        try {
            // 1. 创建 SDP Offer
            $sdpOffer = $this->createSdpOffer();
            $this->logger->debug('Created SDP Offer', ['sdp' => $sdpOffer]);

            // 2. 创建并发送 SIP INVITE
            $request = $this->createSipInvite($sdpOffer, $roomName, $userId);
            $this->logger->debug('Created SIP INVITE request', [
                'request' => $this->formatSipMessage($request)
            ]);

            // 3. 发送请求并等待响应
            $response = $this->sendRequest($request);
            $this->logger->debug('Received SIP response', [
                'response' => $response ? $this->formatSipMessage($response) : 'null'
            ]);

            // 4. 解析 SDP Answer
            $sessionInfo = $this->parseSdpAnswer($response);
            $this->logger->info('Room session created successfully', [
                'roomName' => $roomName,
                'userId' => $userId,
                'sessionInfo' => $sessionInfo
            ]);

            return $sessionInfo;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create room session', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new GatewayException('Failed to create room session: ' . $e->getMessage());
        }
    }

    private function createSdpOffer(): string
    {
        $this->logger->debug('Creating SDP Offer');
        $sdp = [];

        // SDP Version
        $sdp[] = "v=0";

        // Origin
        $sdp[] = sprintf("o=php-sip %d %d IN IP4 0.0.0.0", time(), time());

        // Session Name
        $sdp[] = "s=RTP Bridge Session";

        // Timing
        $sdp[] = "t=0 0";

        // Connection Information
        $sdp[] = "c=IN IP4 0.0.0.0";

        // Media Description - Audio
        $sdp[] = "m=audio 10000 RTP/AVP 0 8 101";
        $sdp[] = "a=rtpmap:0 PCMU/8000";
        $sdp[] = "a=rtpmap:8 PCMA/8000";
        $sdp[] = "a=rtpmap:101 telephone-event/8000";

        // Media Description - Video
        $sdp[] = "m=video 10002 RTP/AVP 96 97";
        $sdp[] = "a=rtpmap:96 H264/90000";
        $sdp[] = "a=rtpmap:97 VP8/90000";

        return implode("\r\n", $sdp) . "\r\n";
    }

    private function createSipInvite(string $sdpOffer, string $roomName, string $userId): Message
    {
        $janusHost = $this->config->get('JANUS_HOST');
        $janusPort = $this->config->get('JANUS_PORT');

        $this->logger->debug('Creating SIP INVITE', [
            'host' => $janusHost,
            'port' => $janusPort,
            'roomName' => $roomName,
            'userId' => $userId
        ]);

        // 创建 INVITE 请求
        $request = new Request("INVITE sip:room_{$roomName}@{$janusHost}:{$janusPort} SIP/2.0");

        // Via 头部
        $via = new ViaHeader();
        $via->values = [
            'protocol' => 'SIP',
            'version' => '2.0',
            'transport' => 'UDP',
            'host' => $janusHost,
            'port' => $janusPort,
            'branch' => uniqid('z9hG4bK', true)
        ];
        $request->via = $via;

        // Call-ID 头部
        $request->callId = new CallIdHeader();
        $request->callId->value = uniqid('', true);

        // CSeq 头部
        $request->cSeq = new CSeqHeader();
        $request->cSeq->sequence = 1;
        $request->cSeq->method = 'INVITE';

        // Max-Forwards 头部
        $maxForwards = new Header();
        $maxForwards->values = ['70'];
        $request->extraHeaders['Max-Forwards'] = $maxForwards;

        // From 头部
        $request->from = new FromHeader();
        $request->from->uri = new URI("sip:{$userId}@{$janusHost}");
        $request->from->tag = uniqid('', true);

        // To 头部
        $toHeader = new Header();
        $toUri = new URI("sip:room_{$roomName}@{$janusHost}");
        $toHeader->values = [$toUri->render()];
        $request->extraHeaders['To'] = $toHeader;

        // Contact 头部
        $contactHeader = new Header();
        $contactHeader->values = [$request->from->uri->render()];
        $request->extraHeaders['Contact'] = $contactHeader;

        // Content-Type 头部
        $contentTypeHeader = new Header();
        $contentTypeHeader->values = ['application/sdp'];
        $request->extraHeaders['Content-Type'] = $contentTypeHeader;

        // Content-Length 头部
        $contentLengthHeader = new Header();
        $contentLengthHeader->values = [(string)strlen($sdpOffer)];
        $request->extraHeaders['Content-Length'] = $contentLengthHeader;

        // 设置消息体
        $request->body = $sdpOffer;

        return $request;
    }

    private function formatSipMessage(Message $message): string
    {
        $output = [];

        // 添加请求行或状态行
        if ($message instanceof Request) {
            $output[] = "{$message->method} {$message->uri->render()} SIP/2.0";
        }

        // 添加 Via 头部
        if (isset($message->via)) {
            $output[] = $message->via->render('Via');
        }

        // 添加 From 头部
        if (isset($message->from)) {
            $output[] = $message->from->render('From');
        }

        // 添加 To 头部
        if (isset($message->extraHeaders['To'])) {
            $output[] = $message->extraHeaders['To']->render('To');
        }

        // 添加 Call-ID 头部
        if (isset($message->callId)) {
            $output[] = $message->callId->render('Call-ID');
        }

        // 添加 CSeq 头部
        if (isset($message->cSeq)) {
            $output[] = $message->cSeq->render('CSeq');
        }

        // 添加其他头部
        foreach ($message->extraHeaders as $name => $header) {
            if ($name !== 'To') { // To 头部已经处理过了
                $output[] = $header->render($name);
            }
        }

        // 添加消息体
        if (!empty($message->body)) {
            $output[] = '';  // 空行分隔头部和消息体
            $output[] = $message->body;
        }

        return implode("\r\n", $output);
    }

    private function sendRequest(Message $request): ?Message
    {
        if (!$this->socket) {
            throw new GatewayException('Socket not initialized');
        }

        try {
            $janusHost = $this->config->get('JANUS_HOST');
            $janusPort = $this->config->get('JANUS_PORT');

            $requestStr = $this->formatSipMessage($request);
            $this->logger->debug('Sending SIP request', [
                'destination' => "{$janusHost}:{$janusPort}",
                'request' => $requestStr
            ]);

            $sent = socket_sendto(
                $this->socket,
                $requestStr,
                strlen($requestStr),
                0,
                $janusHost,
                (int)$janusPort
            );

            if ($sent === false) {
                throw new GatewayException('Failed to send request: ' . socket_strerror(socket_last_error($this->socket)));
            }

            // 接收响应
            $response = '';
            $from = '';
            $port = 0;

            $received = socket_recvfrom(
                $this->socket,
                $response,
                65535,
                0,
                $from,
                $port
            );

            if ($received === false) {
                throw new GatewayException('Failed to receive response: ' . socket_strerror(socket_last_error($this->socket)));
            }

            $this->logger->debug('Received SIP response', [
                'from' => "{$from}:{$port}",
                'response' => $response
            ]);

            return Message::parse($response);
        } catch (\Exception $e) {
            $this->logger->error('Error in SIP communication', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new GatewayException('SIP communication error: ' . $e->getMessage());
        }
    }

    private function parseSdpAnswer($response): array
    {
        $this->logger->debug('Parsing SDP Answer', [
            'response' => $response ? $this->formatSipMessage($response) : 'null'
        ]);

        if (!$response || !$response->body) {
            throw new GatewayException('Empty SDP answer received');
        }

        $sdpAnswer = $response->body;
        $mediaInfo = [];
        $connectionAddress = null;

        // 解析 SDP
        $lines = explode("\r\n", $sdpAnswer);
        $currentMedia = null;

        foreach ($lines as $line) {
            if (strpos($line, 'm=') === 0) {
                // 媒体行
                $parts = explode(' ', $line);
                $currentMedia = substr($parts[0], 2);
                $mediaInfo[$currentMedia] = [
                    'port' => (int)$parts[1],
                    'protocol' => $parts[2],
                    'formats' => array_slice($parts, 3)
                ];
            } elseif (strpos($line, 'c=') === 0 && !isset($connectionAddress)) {
                // 连接信息行
                $parts = explode(' ', $line);
                $connectionAddress = end($parts);
            }
        }

        return [
            'roomId' => uniqid('room_', true),
            'ip' => $connectionAddress ?? '0.0.0.0',
            'mediaInfo' => $mediaInfo,
            'timestamp' => time()
        ];
    }

    public function __destruct()
    {
        if ($this->socket) {
            socket_close($this->socket);
            $this->logger->debug('SIP socket closed');
        }
    }
}
