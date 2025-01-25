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
    SingleValueWithParamsHeader,
    ViaValue
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

        // 设置接收超时
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, [
            'sec' => 5,  // 5 秒超时
            'usec' => 0
        ]);

        // 设置发送超时
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, [
            'sec' => 5,
            'usec' => 0
        ]);

        // 绑定到本地任意地址
        if (!socket_bind($this->socket, '0.0.0.0', 0)) {
            $error = socket_last_error($this->socket);
            $this->logger->error('Failed to bind socket', [
                'error' => socket_strerror($error),
                'ip' => '0.0.0.0'
            ]);
            throw new GatewayException('Failed to bind socket: ' . socket_strerror($error));
        }

        // 记录绑定信息
        socket_getsockname($this->socket, $boundAddr, $boundPort);
        $this->logger->debug('Socket bound successfully', [
            'bound_addr' => $boundAddr,
            'bound_port' => $boundPort,
            'janus_host' => $this->config->get('JANUS_HOST')
        ]);
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
            $viaValue = new ViaValue();
            $viaValue->protocol = 'SIP';
            $viaValue->version = '2.0';
            $viaValue->transport = 'UDP';
            $viaValue->host = $this->config->get('JANUS_HOST');
            $viaValue->branch = 'z9hG4bK' . uniqid();
            $viaValue->rport = 0;  // 0 表示 ;rport 参数没有值
            $via->values[] = $viaValue;
            $invite->via = $via;

            // 创建 From URI
            $fromUri = new URI();
            $fromUri->scheme = 'sip';
            $fromUri->user = $userId;
            $fromUri->host = $this->config->get('JANUS_HOST');

            $from = new FromHeader();
            $from->uri = $fromUri;
            $invite->from = $from;

            // 创建 To URI
            $toUri = new URI();
            $toUri->scheme = 'sip';
            $toUri->user = "room_{$roomName}";
            $toUri->host = $this->config->get('JANUS_HOST');

            $to = new FromHeader();
            $to->uri = $toUri;
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

        // 获取本地 socket 信息
        socket_getsockname($this->socket, $localAddr, $localPort);

        $this->logger->debug('Sending SIP request', [
            'message' => $message,
            'host' => $this->config->get('JANUS_HOST'),
            'port' => 5060,
            'socket_info' => [
                'local_addr' => $localAddr,
                'local_port' => $localPort,
                'remote_host' => gethostbyname($this->config->get('JANUS_HOST')),
                'remote_port' => 5060
            ]
        ]);

        $result = socket_sendto(
            $this->socket,
            $message,
            strlen($message),
            0,
            $this->config->get('JANUS_HOST'),
            5060
        );

        if ($result === false) {
            $errorCode = socket_last_error($this->socket);
            throw new GatewayException(
                'Failed to send request: ' . socket_strerror($errorCode)
            );
        }
    }

    private function waitForResponse(): ?Response
    {
        $this->logger->debug('Waiting for SIP response...');

        try {
            $buf = '';
            $from = '';
            $port = 0;
            $startTime = time();
            $timeout = 60; // 5 秒超时

            while (time() - $startTime < $timeout) {
                // 使用 select 检查 socket 是否可读
                $read = [$this->socket];
                $write = null;
                $except = null;
                $tv_sec = 1;
                $tv_usec = 0;

                $result = socket_select($read, $write, $except, $tv_sec, $tv_usec);

                if ($result === false) {
                    $errorCode = socket_last_error($this->socket);
                    $errorMsg = socket_strerror($errorCode);
                    $this->logger->error('Select failed', [
                        'error' => $errorMsg,
                        'code' => $errorCode
                    ]);
                    throw new GatewayException('Socket select failed: ' . $errorMsg);
                }

                if ($result > 0) {
                    // 有数据可读
                    $result = @socket_recvfrom($this->socket, $buf, 65535, 0, $from, $port);

                    if ($result === false) {
                        $errorCode = socket_last_error($this->socket);
                        $errorMsg = socket_strerror($errorCode);
                        $this->logger->error('Failed to receive response', [
                            'error' => $errorMsg,
                            'code' => $errorCode
                        ]);
                        throw new GatewayException('Failed to receive SIP response: ' . $errorMsg);
                    }

                    if (!empty($buf)) {
                        $this->logger->debug('Received SIP response', [
                            'from' => $from,
                            'port' => $port,
                            'response' => $buf
                        ]);
                        return Response::parse($buf);
                    }
                }
            }

            throw new GatewayException('Timeout waiting for SIP response');
        } catch (\Exception $e) {
            $this->logger->error('Failed to receive SIP response', [
                'error' => $e->getMessage()
            ]);
            throw new GatewayException(
                'SIP communication failed',
                500,
                $e
            );
        }
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
