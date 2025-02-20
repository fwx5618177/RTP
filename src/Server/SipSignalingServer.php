<?php

declare(strict_types=1);

namespace App\Server;

use App\Logs\Logger;
use App\Services\AsteriskService;
use App\Media\MediaManager;
use Swoole\Server;

class SipSignalingServer
{
    private Server $server;
    private Logger $logger;
    private AsteriskService $asteriskService;
    private MediaManager $mediaManager;
    private array $activeCalls = [];

    public function __construct(
        AsteriskService $asteriskService,
        MediaManager $mediaManager
    ) {
        $this->logger = Logger::getInstance('sip-signaling');
        $this->asteriskService = $asteriskService;
        $this->mediaManager = $mediaManager;
    }

    public function start(): void
    {
        $this->server = new Server('0.0.0.0', 5060, SWOOLE_BASE, SWOOLE_SOCK_UDP);

        $this->server->set([
            'worker_num' => 1,
            'daemonize' => false,
            'log_level' => SWOOLE_LOG_INFO,
            'log_file' => __DIR__ . '/../../storage/logs/sip_server.log',
        ]);

        $this->server->on('Packet', [$this, 'handleSipPacket']);

        $this->logger->info('SIP Signaling Server starting on port 5060');
        $this->server->start();
    }

    public function handleSipPacket(Server $server, string $data, array $clientInfo): void
    {
        try {
            $sipMessage = $this->parseSipMessage($data);
            $method = $sipMessage['method'] ?? '';
            $callId = $sipMessage['headers']['Call-ID'] ?? '';

            $this->logger->info('Received SIP packet', [
                'method' => $method,
                'callId' => $callId,
                'from' => $clientInfo
            ]);

            switch ($method) {
                case 'INVITE':
                    $this->handleInvite($server, $sipMessage, $clientInfo);
                    break;
                case 'ACK':
                    $this->handleAck($server, $sipMessage, $clientInfo);
                    break;
                case 'BYE':
                    $this->handleBye($server, $sipMessage, $clientInfo);
                    break;
                case 'CANCEL':
                    $this->handleCancel($server, $sipMessage, $clientInfo);
                    break;
                default:
                    if (str_starts_with($sipMessage['start_line'], 'SIP/2.0')) {
                        $this->handleSipResponse($server, $sipMessage, $clientInfo);
                    }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error handling SIP packet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function handleInvite(Server $server, array $sipMessage, array $clientInfo): void
    {
        $callId = $sipMessage['headers']['Call-ID'];
        $to = $this->parseUri($sipMessage['headers']['To']);
        $from = $this->parseUri($sipMessage['headers']['From']);
        $sdp = $sipMessage['body'] ?? '';

        // 解析目标房间号（从To头域或Request-URI）
        $roomId = $this->extractRoomId($to['uri']);

        // 添加 SDP 协商的详细日志
        $this->logger->debug('SDP Offer received', ['sdp' => $sdp]);

        try {
            // 从 Janus 获取 RTP 端点信息
            $janusRtpInfo = $this->mediaManager->getJanusRtpInfo($roomId);
            $this->logger->debug('Janus RTP endpoint info', ['info' => $janusRtpInfo]);

            // 创建包含 Janus RTP 端点信息的 SDP 应答
            $sdpAnswer = $this->createSdpAnswer($sdp, $janusRtpInfo);
            $this->logger->debug('SDP Answer created', ['sdp' => $sdpAnswer]);

            // 发送200 OK响应
            $response = $this->create200OkResponse($sipMessage, $sdpAnswer);
            $server->sendto($clientInfo['address'], $clientInfo['port'], $response);

            // 保存呼叫信息
            $this->activeCalls[$callId] = [
                'roomId' => $roomId,
                'from' => $from,
                'to' => $to,
                'clientInfo' => $clientInfo,
                'janusRtpInfo' => $janusRtpInfo,
                'state' => 'invited',
                'timestamp' => time()
            ];

            $this->logger->info('INVITE handled successfully', [
                'callId' => $callId,
                'roomId' => $roomId,
                'rtpEndpoint' => [
                    'ip' => $janusRtpInfo['ip'],
                    'port' => $janusRtpInfo['port']
                ]
            ]);
        } catch (\Exception $e) {
            // 发送失败响应
            $response = $this->create500Response($sipMessage);
            $server->sendto($clientInfo['address'], $clientInfo['port'], $response);

            $this->logger->error('Failed to handle INVITE', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);
        }
    }

    private function handleAck(Server $server, array $sipMessage, array $clientInfo): void
    {
        $callId = $sipMessage['headers']['Call-ID'];

        if (isset($this->activeCalls[$callId])) {
            $call = $this->activeCalls[$callId];

            try {
                // 更新呼叫状态
                $this->activeCalls[$callId]['state'] = 'established';

                // 启动 RTP 转发
                $this->mediaManager->startRtpForwarding(
                    $callId,
                    $call['janusRtpInfo']['ip'],
                    $call['janusRtpInfo']['port'],
                    $clientInfo['address'],
                    $this->extractRtpPort($call['sdp'])
                );

                $this->logger->info('ACK handled, RTP forwarding started', [
                    'callId' => $callId,
                    'roomId' => $call['roomId'],
                    'rtpEndpoints' => [
                        'janus' => [
                            'ip' => $call['janusRtpInfo']['ip'],
                            'port' => $call['janusRtpInfo']['port']
                        ],
                        'asterisk' => [
                            'ip' => $clientInfo['address'],
                            'port' => $this->extractRtpPort($call['sdp'])
                        ]
                    ]
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to start RTP forwarding', [
                    'error' => $e->getMessage(),
                    'callId' => $callId
                ]);
            }
        }
    }

    private function handleBye(Server $server, array $sipMessage, array $clientInfo): void
    {
        $callId = $sipMessage['headers']['Call-ID'];

        if (isset($this->activeCalls[$callId])) {
            try {
                // 停止RTP转发
                $this->mediaManager->stopRtpForwarding($callId);

                // 发送200 OK响应
                $response = $this->create200OkResponse($sipMessage);
                $server->sendto($clientInfo['address'], $clientInfo['port'], $response);

                // 清理呼叫记录
                unset($this->activeCalls[$callId]);

                $this->logger->info('BYE handled successfully', [
                    'callId' => $callId
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to handle BYE', [
                    'error' => $e->getMessage(),
                    'callId' => $callId
                ]);
            }
        }
    }

    private function handleCancel(Server $server, array $sipMessage, array $clientInfo): void
    {
        $callId = $sipMessage['headers']['Call-ID'];

        if (isset($this->activeCalls[$callId])) {
            // 发送200 OK响应
            $response = $this->create200OkResponse($sipMessage);
            $server->sendto($clientInfo['address'], $clientInfo['port'], $response);

            // 清理呼叫记录
            unset($this->activeCalls[$callId]);

            $this->logger->info('CANCEL handled successfully', [
                'callId' => $callId
            ]);
        }
    }

    private function handleSipResponse(Server $server, array $sipMessage, array $clientInfo): void
    {
        $callId = $sipMessage['headers']['Call-ID'];
        $statusCode = (int)substr($sipMessage['start_line'], 8, 3);

        $this->logger->info('Handling SIP response', [
            'callId' => $callId,
            'statusCode' => $statusCode
        ]);

        if (isset($this->activeCalls[$callId])) {
            $call = $this->activeCalls[$callId];

            try {
                $this->asteriskService->handleSipResponse($callId, $statusCode, $sipMessage['headers']);
            } catch (\Exception $e) {
                $this->logger->error('Failed to handle SIP response', [
                    'error' => $e->getMessage(),
                    'callId' => $callId
                ]);
            }
        }
    }

    private function parseSipMessage(string $data): array
    {
        $lines = explode("\r\n", $data);
        $message = [
            'start_line' => $lines[0],
            'headers' => [],
            'body' => ''
        ];

        // 解析方法
        if (!str_starts_with($lines[0], 'SIP/2.0')) {
            preg_match('/^(\w+)/', $lines[0], $matches);
            $message['method'] = $matches[1] ?? '';
        }

        $i = 1;
        // 解析头域
        while ($i < count($lines) && !empty($lines[$i])) {
            if (preg_match('/^([\w-]+):\s*(.+)$/', $lines[$i], $matches)) {
                $message['headers'][$matches[1]] = $matches[2];
            }
            $i++;
        }

        // 解析消息体
        $i++;
        if ($i < count($lines)) {
            $message['body'] = implode("\r\n", array_slice($lines, $i));
        }

        return $message;
    }

    private function parseUri(string $header): array
    {
        preg_match('/<sip:([^@>]+)(?:@([^>]+))?>/i', $header, $matches);
        return [
            'uri' => $matches[0] ?? '',
            'user' => $matches[1] ?? '',
            'host' => $matches[2] ?? ''
        ];
    }

    private function extractRoomId(string $uri): int
    {
        // 从SIP URI中提取房间号
        // 例如：<sip:9100@pbx.example.com> 中的9100
        preg_match('/sip:(\d+)@/', $uri, $matches);
        return (int)($matches[1] ?? 0);
    }

    private function createSdpAnswer(string $offerSdp, array $janusRtpInfo): string
    {
        // 解析 SDP offer
        $lines = explode("\r\n", $offerSdp);
        $parsedOffer = [
            'codecs' => [],
            'media' => []
        ];

        // 解析 offer 中的编解码器信息
        foreach ($lines as $line) {
            if (strpos($line, 'a=rtpmap:') === 0) {
                preg_match('/a=rtpmap:(\d+)\s+([^\s\/]+)\/(\d+)(?:\/(\d+))?/', $line, $matches);
                if ($matches) {
                    $parsedOffer['codecs'][] = [
                        'payload' => (int)$matches[1],
                        'name' => $matches[2],
                        'rate' => (int)$matches[3],
                        'channels' => isset($matches[4]) ? (int)$matches[4] : 1
                    ];
                }
            }
        }

        // 创建 SDP 应答
        $sdp = [];
        $sdp[] = "v=0";
        $sdp[] = "o=- " . time() . " " . time() . " IN IP4 " . $janusRtpInfo['ip'];
        $sdp[] = "s=RTP Bridge";
        $sdp[] = "c=IN IP4 " . $janusRtpInfo['ip'];
        $sdp[] = "t=0 0";
        $sdp[] = "m=audio " . $janusRtpInfo['port'] . " RTP/AVP 111 0 8 101";
        $sdp[] = "a=rtpmap:111 opus/48000/2";
        $sdp[] = "a=rtpmap:0 PCMU/8000";
        $sdp[] = "a=rtpmap:8 PCMA/8000";
        $sdp[] = "a=rtpmap:101 telephone-event/8000";
        $sdp[] = "a=fmtp:101 0-15";
        $sdp[] = "a=sendrecv";
        $sdp[] = "a=ptime:20";

        return implode("\r\n", $sdp) . "\r\n";
    }

    private function create200OkResponse(array $request, ?string $sdp = null): string
    {
        $response = [];
        $response[] = "SIP/2.0 200 OK";

        // 复制必要的头域
        foreach (['Via', 'From', 'To', 'Call-ID', 'CSeq'] as $header) {
            if (isset($request['headers'][$header])) {
                $response[] = "$header: " . $request['headers'][$header];
            }
        }

        $response[] = "Contact: <sip:rtp-bridge@" . gethostname() . ":5060>";
        $response[] = "Content-Type: application/sdp";
        $response[] = "Content-Length: " . ($sdp ? strlen($sdp) : 0);
        $response[] = "";

        if ($sdp) {
            $response[] = $sdp;
        }

        return implode("\r\n", $response);
    }

    private function create500Response(array $request): string
    {
        $response = [];
        $response[] = "SIP/2.0 500 Server Internal Error";

        foreach (['Via', 'From', 'To', 'Call-ID', 'CSeq'] as $header) {
            if (isset($request['headers'][$header])) {
                $response[] = "$header: " . $request['headers'][$header];
            }
        }

        $response[] = "Content-Length: 0";
        $response[] = "";

        return implode("\r\n", $response);
    }

    private function negotiateSdp(string $offerSdp, int $roomId): array
    {
        try {
            // 1. 从 Janus 获取房间的 RTP 端点信息
            $janusRtpInfo = $this->mediaManager->getJanusRtpInfo($roomId);

            // 2. 直接使用原始的 SDP offer 字符串创建应答
            $answer = $this->createSdpAnswer($offerSdp, $janusRtpInfo);

            return [
                'sdp' => $answer,
                'rtpInfo' => $janusRtpInfo
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to negotiate SDP', [
                'error' => $e->getMessage(),
                'roomId' => $roomId
            ]);
            throw new \Exception('Failed to negotiate SDP: ' . $e->getMessage());
        }
    }

    private function setupRtpEndpoint(string $callId, array $janusRtpInfo, array $asteriskRtpInfo): void
    {
        // 添加详细的 RTP 配置验证
        if (!isset($janusRtpInfo['ip']) || !isset($janusRtpInfo['port'])) {
            throw new \Exception('Invalid Janus RTP endpoint information');
        }

        // 设置双向 RTP 转发
        $this->mediaManager->setupRtpForwarding($callId, [
            'asterisk' => $asteriskRtpInfo,
            'janus' => $janusRtpInfo
        ]);
    }

    private function updateCallState(string $callId, string $state, array $data = []): void
    {
        if (!isset($this->activeCalls[$callId])) {
            $this->activeCalls[$callId] = [];
        }

        $this->activeCalls[$callId] = array_merge($this->activeCalls[$callId], [
            'state' => $state,
            'timestamp' => time(),
            'data' => $data
        ]);
    }

    private function parseSdp(string $sdp): array
    {
        $lines = explode("\r\n", $sdp);
        $parsed = [
            'version' => 0,
            'media' => [],
            'attributes' => [],
            'codecs' => []
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $type = substr($line, 0, 1);
            $content = substr($line, 2);

            switch ($type) {
                case 'v':
                    $parsed['version'] = (int)$content;
                    break;
                case 'o':
                    $parsed['origin'] = $content;
                    break;
                case 's':
                    $parsed['session_name'] = $content;
                    break;
                case 'c':
                    $parts = explode(' ', $content);
                    $parsed['connection'] = [
                        'ip' => $parts[2]
                    ];
                    break;
                case 'm':
                    $parts = explode(' ', $content);
                    $parsed['media'][] = [
                        'type' => $parts[0],
                        'port' => (int)$parts[1],
                        'protocol' => $parts[2],
                        'formats' => array_slice($parts, 3)
                    ];
                    break;
                case 'a':
                    if (strpos($content, 'rtpmap:') === 0) {
                        $rtpmap = substr($content, 7);
                        $parts = explode(' ', $rtpmap);
                        $codec = explode('/', $parts[1]);
                        $parsed['codecs'][] = [
                            'payload' => (int)$parts[0],
                            'name' => $codec[0],
                            'rate' => isset($codec[1]) ? (int)$codec[1] : null,
                            'channels' => isset($codec[2]) ? (int)$codec[2] : null
                        ];
                    }
                    $parsed['attributes'][] = $content;
                    break;
            }
        }

        return $parsed;
    }

    private function extractRtpPort(string $sdp): int
    {
        if (preg_match('/m=audio (\d+) RTP/', $sdp, $matches)) {
            return (int)$matches[1];
        }
        throw new \Exception('Could not extract RTP port from SDP');
    }
}
