<?php

declare(strict_types=1);

namespace App\Media;

use App\Config\Config;
use App\Exceptions\MediaException;
use App\Gateway\JanusGateway;
use App\Logs\Logger;
use RTCKit\SDP\{Parser};

/**
 * MediaManager 类
 *
 * 负责处理所有媒体相关的操作，包括：
 * - SDP 协商（Offer/Answer）
 * - RTP 音频流配置
 * - 媒体编解码器配置
 */
class MediaManager
{
    private Logger $logger;
    private Config $config;
    private JanusGateway $janusGateway;
    private ?string $sessionId = null;
    private ?string $handleId = null;
    private ?int $currentRoomId = null;

    /**
     * 默认的音频编解码器配置
     */
    private const DEFAULT_AUDIO_CODECS = [
        ['payload' => 0, 'name' => 'PCMU', 'rate' => 8000, 'channels' => 1],  // G.711 u-law
        ['payload' => 8, 'name' => 'PCMA', 'rate' => 8000, 'channels' => 1],  // G.711 a-law
        ['payload' => 101, 'name' => 'telephone-event', 'rate' => 8000, 'channels' => 1, 'fmtp' => '0-15'],  // DTMF
        ['payload' => 111, 'name' => 'opus', 'rate' => 48000, 'channels' => 2, 'fmtp' => 'minptime=10;useinbandfec=1']  // Opus
    ];

    private array $activeMediaSessions = [];
    private array $rtpSockets = [];
    private array $forwardingProcesses = [];

    public function __construct()
    {
        $this->logger = Logger::getInstance('media-manager');
        $this->config = Config::getInstance();
        $this->janusGateway = new JanusGateway();
    }

    /**
     * 创建媒体会话
     *
     * @param string $roomName 房间名称
     * @param string $userId 用户ID
     * @return array 会话信息，包含 RTP 端口和编解码器信息
     * @throws MediaException
     */
    public function createMediaSession(string $roomName, string $userId): array
    {
        try {
            $this->logger->info('Creating media session', [
                'roomName' => $roomName,
                'userId' => $userId,
            ]);

            // 创建 SDP Offer
            $sdpOffer = $this->createSdpOffer();
            $this->logger->debug('Created SDP offer', ['sdp' => $sdpOffer]);
            // 通过 Janus Gateway 发送 SIP INVITE
            $sessionInfo = $this->janusGateway->createAudioRoom($roomName, $userId, [$sdpOffer]);
            $this->logger->debug('Received session info', ['info' => json_encode($sessionInfo)]);

            // 解析 SDP Answer
            if (! isset($sessionInfo['sdpAnswer'])) {
                throw new MediaException('Missing SDP answer in session info');
            }
            $mediaInfo = $this->parseSdpAnswer($sessionInfo['sdpAnswer']);

            return [
                'roomId' => $sessionInfo['roomId'],
                'mediaInfo' => $mediaInfo,
                'ip' => $sessionInfo['ip'],
                'timestamp' => time(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create media session', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MediaException('Failed to create media session: ' . $e->getMessage());
        }
    }

    /**
     * 创建 SDP Offer
     * 生成用于音频桥接的 SDP 描述
     *
     * @return string SDP 字符串
     * @throws MediaException
     */
    private function createSdpOffer(): string
    {
        try {
            $sdp = [];
            // 会话级别属性
            $sdp[] = "v=0";
            $sdp[] = sprintf(
                "o=php-sdp %d %d IN IP4 %s",
                time(),
                time(),
                $this->config->get('JANUS_HOST')
            );
            $sdp[] = "s=RTP Audio Bridge Session";
            $sdp[] = "t=0 0";
            $sdp[] = sprintf("c=IN IP4 %s", $this->config->get('JANUS_HOST'));

            // 添加更多会话级属性
            $sdp[] = "a=tool:php-sdp";
            $sdp[] = "a=ice-lite";  // 表明是服务器端
            $sdp[] = "a=msid-semantic: WMS *";

            // 音频媒体描述
            $sdp[] = sprintf("m=audio %d RTP/AVP 0 8 101", (int)$this->config->get('JANUS_SIP_PORT'));

            // 添加音频编解码器和其他属性
            foreach (self::DEFAULT_AUDIO_CODECS as $codec) {
                $sdp[] = sprintf(
                    "a=rtpmap:%d %s/%d%s",
                    $codec['payload'],
                    $codec['name'],
                    $codec['rate'],
                    isset($codec['channels']) && $codec['channels'] > 1 ? '/' . $codec['channels'] : ''
                );

                if (isset($codec['fmtp'])) {
                    $sdp[] = sprintf("a=fmtp:%d %s", $codec['payload'], $codec['fmtp']);
                }
            }

            // 添加更多音频相关属性
            $sdp[] = "a=sendrecv";
            $sdp[] = "a=maxptime:20";  // 最大打包时间
            $sdp[] = "a=ptime:20";     // 推荐打包时间
            $sdp[] = "a=ssrc:1234 cname:php-sdp";
            $sdp[] = "a=ssrc:1234 msid:audio0 track0";

            $sdpString = implode("\r\n", $sdp) . "\r\n";

            // 验证 SDP
            $parser = new Parser();
            $parser->parse($sdpString);

            return $sdpString;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create SDP offer', [
                'error' => $e->getMessage(),
            ]);

            throw new MediaException('Failed to create SDP offer: ' . $e->getMessage());
        }
    }

    /**
     * 解析 SDP Answer
     * 解析 Janus Gateway 返回的 SDP 应答
     *
     * @param string $sdpAnswer SDP Answer 字符串
     * @return array 解析后的媒体信息
     * @throws MediaException
     */
    private function parseSdpAnswer(string $sdpAnswer): array
    {
        try {
            $parser = new Parser();
            $sdp = $parser->parse($sdpAnswer);

            $mediaInfo = [];
            $lines = explode("\r\n", $sdpAnswer);
            $currentMedia = null;

            foreach ($lines as $line) {
                if (strpos($line, 'm=') === 0) {
                    // 媒体行解析
                    $parts = explode(' ', $line);
                    $currentMedia = substr($parts[0], 2);
                    $mediaInfo[$currentMedia] = [
                        'port' => (int)$parts[1],
                        'protocol' => $parts[2],
                        'formats' => array_slice($parts, 3),
                    ];
                } elseif (strpos($line, 'c=') === 0) {
                    // 连接信息行
                    $parts = explode(' ', $line);
                    $mediaInfo['connection'] = [
                        'ip' => end($parts),
                    ];
                }
            }

            return $mediaInfo;
        } catch (\Exception $e) {
            $this->logger->error('Failed to parse SDP answer', [
                'error' => $e->getMessage(),
                'sdpAnswer' => $sdpAnswer,
            ]);

            throw new MediaException('Failed to parse SDP answer: ' . $e->getMessage());
        }
    }

    public function createAudioRoom(string $roomName, string $userId): array
    {
        try {
            // 1. 创建 Janus 会话
            $session = $this->janusGateway->createSession();
            $this->sessionId = (string)$session['data']['id'];

            // 2. 附加到 AudioBridge 插件
            $handle = $this->janusGateway->attachPlugin($this->sessionId);
            $this->handleId = (string)$handle['data']['id'];

            // 3. 创建音频房间
            $roomId = rand(1000000, 9999999); // 生成一个随机的房间ID
            $roomConfig = [
                'roomId' => $roomId,  // 修改这里，使用 roomId 作为键名
                'description' => $roomName,
                'sampling_rate' => 16000,
                'spatial_audio' => false,
                'record' => false,
                'notify_joining' => true,
            ];

            $response = $this->janusGateway->createAudioRoom(
                $this->sessionId,
                $this->handleId,
                $roomConfig
            );

            // 直接返回必要的信息
            return [
                'sessionId' => $this->sessionId,
                'handleId' => $this->handleId,
                'roomId' => $roomId,
                'janus_session_id' => $this->sessionId,
                'janus_handle_id' => $this->handleId,
                'config' => $roomConfig,
                'janusResponse' => $response
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create audio room', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MediaException('Failed to create audio room: ' . $e->getMessage());
        }
    }

    public function joinAudioRoom(int $roomId, string $userId, string $display): array
    {
        try {
            // 检查是否已经在房间中
            if ($this->currentRoomId === $roomId) {
                $this->logger->warning('Already in room', [
                    'roomId' => $roomId,
                    'userId' => $userId
                ]);
                throw new MediaException('Already in this room');
            }

            if (! $this->sessionId) {
                $session = $this->janusGateway->createSession();
                $this->sessionId = (string)$session['data']['id'];
            }

            if (! $this->handleId) {
                $handle = $this->janusGateway->attachPlugin($this->sessionId);
                $this->handleId = (string)$handle['data']['id'];
            }

            $response = $this->janusGateway->joinAudioRoom(
                $this->sessionId,
                $this->handleId,
                $roomId,
                $display
            );

            // 保存当前房间ID
            $this->currentRoomId = $roomId;

            return [
                'roomId' => $roomId,
                'sessionId' => $this->sessionId,
                'handleId' => $this->handleId,
                'janusResponse' => $response,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to join audio room', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new MediaException('Failed to join audio room: ' . $e->getMessage());
        }
    }

    /**
     * 处理 SIP 到 WebRTC 的媒体转换
     */
    public function handleSipToWebRtc(array $sipMedia, array $webrtcMedia): array
    {
        $this->logger->debug('Converting SIP to WebRTC media', [
            'sip' => $sipMedia,
            'webrtc' => $webrtcMedia
        ]);

        return [
            'audio' => [
                'codecs' => $this->getCompatibleAudioCodecs($sipMedia['audio']['codecs'] ?? [], $webrtcMedia['audio']['codecs'] ?? []),
                'rtpmap' => $this->generateRtpMap($sipMedia['audio']['codecs'] ?? []),
                'fmtp' => $this->generateFmtp($sipMedia['audio']['codecs'] ?? []),
                'direction' => 'sendrecv'
            ],
            'video' => false,
            'data' => false
        ];
    }

    /**
     * 处理 WebRTC 到 SIP 的媒体转换
     */
    public function handleWebRtcToSip(array $webrtcMedia, array $sipMedia): array
    {
        $this->logger->debug('Converting WebRTC to SIP media', [
            'webrtc' => $webrtcMedia,
            'sip' => $sipMedia
        ]);

        return [
            'audio' => [
                'codecs' => $this->getCompatibleAudioCodecs($webrtcMedia['audio']['codecs'] ?? [], $sipMedia['audio']['codecs'] ?? []),
                'rtpmap' => $this->generateRtpMap($webrtcMedia['audio']['codecs'] ?? []),
                'fmtp' => $this->generateFmtp($webrtcMedia['audio']['codecs'] ?? []),
                'direction' => 'sendrecv'
            ],
            'video' => false,
            'data' => false
        ];
    }

    /**
     * 获取兼容的音频编解码器
     */
    private function getCompatibleAudioCodecs(array $sourceCodecs, array $targetCodecs): array
    {
        $compatibleCodecs = [];
        foreach ($sourceCodecs as $sourceCodec) {
            foreach ($targetCodecs as $targetCodec) {
                if ($this->isCodecCompatible($sourceCodec, $targetCodec)) {
                    $compatibleCodecs[] = $sourceCodec;
                    break;
                }
            }
        }
        return $compatibleCodecs;
    }

    /**
     * 检查编解码器是否兼容
     */
    private function isCodecCompatible(array $codec1, array $codec2): bool
    {
        // 检查基本编解码器名称是否匹配
        if (strtolower($codec1['name']) !== strtolower($codec2['name'])) {
            return false;
        }

        // 检查采样率是否兼容
        if ($codec1['clockrate'] !== $codec2['clockrate']) {
            return false;
        }

        // 检查通道数是否兼容
        if (
            isset($codec1['channels']) && isset($codec2['channels']) &&
            $codec1['channels'] !== $codec2['channels']
        ) {
            return false;
        }

        return true;
    }

    /**
     * 生成 RTP Map
     */
    private function generateRtpMap(array $codecs): array
    {
        $rtpMap = [];
        foreach ($codecs as $codec) {
            $rtpMap[$codec['pt']] = sprintf(
                '%s/%d%s',
                $codec['name'],
                $codec['clockrate'],
                isset($codec['channels']) ? '/' . $codec['channels'] : ''
            );
        }
        return $rtpMap;
    }

    /**
     * 生成 FMTP
     */
    private function generateFmtp(array $codecs): array
    {
        $fmtp = [];
        foreach ($codecs as $codec) {
            if (isset($codec['fmtp'])) {
                $fmtp[$codec['pt']] = $codec['fmtp'];
            }
        }
        return $fmtp;
    }

    /**
     * 处理 SIP SDP Offer
     */
    public function handleSipSdpOffer(string $sdpOffer, string $callId): array
    {
        try {
            $this->logger->info('Handling SIP SDP offer', [
                'callId' => $callId
            ]);

            // 解析 SDP offer
            $parser = new Parser();
            $parsedOffer = $parser->parse($sdpOffer);

            // 创建 SDP answer
            $sdpAnswer = $this->createSdpAnswer($parsedOffer);

            // 保存媒体会话信息
            $mediaInfo = $this->extractMediaInfo($parsedOffer);
            $this->activeMediaSessions[$callId] = [
                'offer' => $sdpOffer,
                'answer' => $sdpAnswer,
                'mediaInfo' => $mediaInfo,
                'status' => 'negotiating',
                'created' => time()
            ];

            return [
                'sdpAnswer' => $sdpAnswer,
                'mediaInfo' => $mediaInfo
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle SIP SDP offer', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);
            throw new MediaException('Failed to handle SIP SDP offer: ' . $e->getMessage());
        }
    }

    /**
     * 处理 SIP SDP Answer
     */
    public function handleSipSdpAnswer(string $sdpAnswer, string $callId): array
    {
        try {
            $this->logger->info('Handling SIP SDP answer', [
                'callId' => $callId
            ]);

            if (!isset($this->activeMediaSessions[$callId])) {
                throw new MediaException('No active media session found for call ID: ' . $callId);
            }

            // 解析 SDP answer
            $parser = new Parser();
            $parsedAnswer = $parser->parse($sdpAnswer);

            // 更新媒体会话信息
            $mediaInfo = $this->extractMediaInfo($parsedAnswer);
            $this->activeMediaSessions[$callId]['answer'] = $sdpAnswer;
            $this->activeMediaSessions[$callId]['mediaInfo'] = $mediaInfo;
            $this->activeMediaSessions[$callId]['status'] = 'negotiated';

            return [
                'mediaInfo' => $mediaInfo
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle SIP SDP answer', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);
            throw new MediaException('Failed to handle SIP SDP answer: ' . $e->getMessage());
        }
    }

    /**
     * 开始 RTP 转发
     */
    public function startRtpForwarding(string $callId, string $targetIp, int $targetPort): array
    {
        try {
            $this->logger->info('Starting RTP forwarding', [
                'callId' => $callId,
                'targetIp' => $targetIp,
                'targetPort' => $targetPort
            ]);

            if (!isset($this->activeMediaSessions[$callId])) {
                throw new MediaException('No active media session found for call ID: ' . $callId);
            }

            $session = $this->activeMediaSessions[$callId];
            if ($session['status'] !== 'negotiated') {
                throw new MediaException('Media session not in negotiated state');
            }

            // 配置 RTP 转发
            $rtpConfig = [
                'sourceIp' => $this->config->get('LOCAL_IP'),
                'sourcePort' => $this->allocateRtpPort(),
                'targetIp' => $targetIp,
                'targetPort' => $targetPort,
                'codec' => $session['mediaInfo']['audio']['codec'],
                'ptime' => $session['mediaInfo']['audio']['ptime'],
                'ssrc' => mt_rand(1, 999999),
                'direction' => 'sendrecv'
            ];

            // 启动 RTP 转发
            $this->startRtpStream($rtpConfig);

            // 更新会话状态
            $this->activeMediaSessions[$callId]['rtp'] = $rtpConfig;
            $this->activeMediaSessions[$callId]['status'] = 'streaming';

            return [
                'rtpConfig' => $rtpConfig
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to start RTP forwarding', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);
            throw new MediaException('Failed to start RTP forwarding: ' . $e->getMessage());
        }
    }

    /**
     * 停止 RTP 转发
     */
    public function stopRtpForwarding(string $callId): array
    {
        try {
            $this->logger->info('Stopping RTP forwarding', [
                'callId' => $callId
            ]);

            if (!isset($this->activeMediaSessions[$callId])) {
                throw new MediaException('No active media session found for call ID: ' . $callId);
            }

            $session = $this->activeMediaSessions[$callId];
            if (!isset($session['rtp'])) {
                throw new MediaException('No RTP configuration found for this session');
            }

            // 停止 RTP 转发
            $this->stopRtpStream($session['rtp']);

            // 更新会话状态
            $this->activeMediaSessions[$callId]['status'] = 'stopped';
            unset($this->activeMediaSessions[$callId]['rtp']);

            return [
                'status' => 'stopped',
                'timestamp' => time()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to stop RTP forwarding', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);
            throw new MediaException('Failed to stop RTP forwarding: ' . $e->getMessage());
        }
    }

    /**
     * 分配 RTP 端口
     */
    private function allocateRtpPort(): int
    {
        // 在可用端口范围内分配端口
        $minPort = (int)$this->config->get('RTP_PORT_MIN', 10000);
        $maxPort = (int)$this->config->get('RTP_PORT_MAX', 20000);

        // 获取已使用的端口
        $usedPorts = array_map(function ($session) {
            return $session['rtp']['sourcePort'] ?? null;
        }, $this->activeMediaSessions);
        $usedPorts = array_filter($usedPorts);

        // 查找可用端口
        for ($port = $minPort; $port <= $maxPort; $port += 2) {
            if (!in_array($port, $usedPorts)) {
                return $port;
            }
        }

        throw new MediaException('No available RTP ports');
    }

    /**
     * 启动 RTP 流
     */
    private function startRtpStream(array $config): void
    {
        try {
            $socketKey = sprintf('%s_%s', $config['callId'], $config['direction']);

            // 创建子进程来处理 RTP 转发
            $pid = pcntl_fork();

            if ($pid == -1) {
                throw new MediaException('Failed to create forwarding process');
            } else if ($pid) {
                // 父进程：记录子进程 PID
                $this->forwardingProcesses[$socketKey] = [
                    'pid' => $pid,
                    'config' => $config,
                    'start_time' => microtime(true)
                ];
                return;
            }

            // 子进程：处理 RTP 转发
            try {
                // 创建 UDP socket
                $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                if ($socket === false) {
                    throw new MediaException('Failed to create socket: ' . socket_strerror(socket_last_error()));
                }

                // 设置 socket 选项
                socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
                socket_set_option($socket, SOL_SOCKET, SO_RCVBUF, 65535);
                socket_set_option($socket, SOL_SOCKET, SO_SNDBUF, 65535);

                // 绑定到源地址和端口
                if (!socket_bind($socket, $config['sourceIp'], $config['sourcePort'])) {
                    throw new MediaException('Failed to bind socket: ' . socket_strerror(socket_last_error($socket)));
                }

                // 设置非阻塞模式
                socket_set_nonblock($socket);

                $stats = [
                    'packets_sent' => 0,
                    'bytes_sent' => 0
                ];

                // 主转发循环
                while (true) {
                    $read = [$socket];
                    $write = null;
                    $except = null;

                    // 使用 select 来等待数据，超时时间 10ms
                    if (socket_select($read, $write, $except, 0, 10000) > 0) {
                        $buffer = '';
                        $from = '';
                        $port = 0;

                        // 接收 RTP 包
                        $received = socket_recvfrom($socket, $buffer, 1500, 0, $from, $port);
                        if ($received !== false) {
                            // 转发 RTP 包
                            $sent = socket_sendto(
                                $socket,
                                $buffer,
                                strlen($buffer),
                                0,
                                $config['targetIp'],
                                $config['targetPort']
                            );

                            if ($sent !== false) {
                                $stats['packets_sent']++;
                                $stats['bytes_sent'] += $sent;
                            }
                        }
                    }

                    // 定期记录统计信息
                    if ($stats['packets_sent'] % 1000 == 0) {
                        $this->logger->debug('RTP forwarding stats', [
                            'socketKey' => $socketKey,
                            'stats' => $stats
                        ]);
                    }

                    // 检查父进程是否存活
                    if (posix_getppid() == 1) {
                        break; // 父进程已终止
                    }
                }

                // 清理
                socket_close($socket);
                exit(0);
            } catch (\Exception $e) {
                $this->logger->error('RTP forwarding process error', [
                    'error' => $e->getMessage(),
                    'socketKey' => $socketKey
                ]);
                exit(1);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to start RTP stream', [
                'error' => $e->getMessage(),
                'config' => $config
            ]);
            throw new MediaException('Failed to start RTP stream: ' . $e->getMessage());
        }
    }

    /**
     * 停止 RTP 流
     */
    private function stopRtpStream(array $config): void
    {
        try {
            $socketKey = sprintf('%s_%s', $config['callId'], $config['direction']);

            if (isset($this->forwardingProcesses[$socketKey])) {
                $process = $this->forwardingProcesses[$socketKey];

                // 终止转发进程
                posix_kill($process['pid'], SIGTERM);
                pcntl_waitpid($process['pid'], $status);

                // 记录统计信息
                $this->logger->info('Stopped RTP stream', [
                    'socketKey' => $socketKey,
                    'duration' => microtime(true) - $process['start_time']
                ]);

                unset($this->forwardingProcesses[$socketKey]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to stop RTP stream', [
                'error' => $e->getMessage(),
                'config' => $config
            ]);
            throw new MediaException('Failed to stop RTP stream: ' . $e->getMessage());
        }
    }

    /**
     * 获取 RTP 流统计信息
     */
    public function getRtpStats(string $callId, string $direction = null): array
    {
        $stats = [];
        $pattern = $direction ? sprintf('%s_%s', $callId, $direction) : $callId . '_';

        foreach ($this->forwardingProcesses as $key => $process) {
            if (strpos($key, $pattern) === 0) {
                $stats[$key] = [
                    'pid' => $process['pid'],
                    'running' => posix_kill($process['pid'], 0),
                    'duration' => microtime(true) - $process['start_time']
                ];
            }
        }

        return $stats;
    }

    /**
     * 配置 Asterisk 到 Janus 的 RTP 转发
     */
    public function setupAsteriskToJanusRtp(string $callId, array $asteriskRtpInfo, array $janusRtpInfo): array
    {
        try {
            $this->logger->info('Setting up Asterisk to Janus RTP forwarding', [
                'callId' => $callId,
                'asterisk' => $asteriskRtpInfo,
                'janus' => $janusRtpInfo
            ]);

            // 配置从 Asterisk 到 Janus 的 RTP 转发
            $asteriskToJanusConfig = [
                'callId' => $callId,
                'sourceIp' => $asteriskRtpInfo['ip'],
                'sourcePort' => $asteriskRtpInfo['port'],
                'targetIp' => $janusRtpInfo['ip'],
                'targetPort' => $janusRtpInfo['port'],
                'direction' => 'asterisk_to_janus'
            ];

            // 配置从 Janus 到 Asterisk 的 RTP 转发
            $janusToAsteriskConfig = [
                'callId' => $callId,
                'sourceIp' => $janusRtpInfo['ip'],
                'sourcePort' => $janusRtpInfo['port'],
                'targetIp' => $asteriskRtpInfo['ip'],
                'targetPort' => $asteriskRtpInfo['port'],
                'direction' => 'janus_to_asterisk'
            ];

            // 启动双向 RTP 转发
            $this->startRtpStream($asteriskToJanusConfig);
            $this->startRtpStream($janusToAsteriskConfig);

            // 保存转发配置
            $this->activeMediaSessions[$callId]['rtp_forwarding'] = [
                'asterisk_to_janus' => $asteriskToJanusConfig,
                'janus_to_asterisk' => $janusToAsteriskConfig
            ];

            return [
                'status' => 'forwarding',
                'asterisk_to_janus' => $asteriskToJanusConfig,
                'janus_to_asterisk' => $janusToAsteriskConfig
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to setup RTP forwarding', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);
            throw new MediaException('Failed to setup RTP forwarding: ' . $e->getMessage());
        }
    }

    /**
     * 停止 Asterisk 到 Janus 的 RTP 转发
     */
    public function stopAsteriskToJanusRtp(string $callId): void
    {
        try {
            if (isset($this->activeMediaSessions[$callId]['rtp_forwarding'])) {
                $forwarding = $this->activeMediaSessions[$callId]['rtp_forwarding'];

                // 停止双向 RTP 转发
                $this->stopRtpStream($forwarding['asterisk_to_janus']);
                $this->stopRtpStream($forwarding['janus_to_asterisk']);

                unset($this->activeMediaSessions[$callId]['rtp_forwarding']);

                $this->logger->info('Stopped RTP forwarding', [
                    'callId' => $callId
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to stop RTP forwarding', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);
            throw new MediaException('Failed to stop RTP forwarding: ' . $e->getMessage());
        }
    }

    /**
     * 从 SDP 中提取媒体信息
     */
    private function extractMediaInfo($parsedSdp): array
    {
        $mediaInfo = [
            'audio' => [
                'codec' => '',
                'ptime' => 20,
                'rate' => 8000,
                'channels' => 1
            ]
        ];

        // 解析媒体行
        foreach ($parsedSdp->getMediaDescriptions() as $media) {
            if ($media->getType() === 'audio') {
                // 获取编解码器信息
                $rtpmap = $media->getRtpMap();
                if (!empty($rtpmap)) {
                    $mediaInfo['audio']['codec'] = $rtpmap[0]['encoding'];
                    $mediaInfo['audio']['rate'] = $rtpmap[0]['rate'];
                    $mediaInfo['audio']['channels'] = $rtpmap[0]['channels'] ?? 1;
                }

                // 获取 ptime
                $fmtp = $media->getFmtp();
                if (!empty($fmtp)) {
                    foreach ($fmtp as $param) {
                        if (strpos($param, 'ptime=') === 0) {
                            $mediaInfo['audio']['ptime'] = (int)substr($param, 6);
                            break;
                        }
                    }
                }
                break;
            }
        }

        return $mediaInfo;
    }

    /**
     * 创建 SDP Answer
     * 根据收到的 SDP Offer 生成对应的 Answer
     */
    private function createSdpAnswer($parsedOffer): string
    {
        try {
            $sdp = [];

            // 会话级别属性
            $sdp[] = "v=0";
            $sdp[] = sprintf(
                "o=php-sdp %d %d IN IP4 %s",
                time(),
                time(),
                $this->config->get('LOCAL_IP')
            );
            $sdp[] = "s=RTP Audio Bridge Answer";
            $sdp[] = "t=0 0";
            $sdp[] = sprintf("c=IN IP4 %s", $this->config->get('LOCAL_IP'));

            // 添加会话级属性
            $sdp[] = "a=msid-semantic: WMS *";

            // 处理音频媒体描述
            foreach ($parsedOffer->getMediaDescriptions() as $media) {
                if ($media->getType() === 'audio') {
                    // 获取支持的编解码器
                    $supportedCodecs = $this->getSupportedCodecs($media);
                    if (empty($supportedCodecs)) {
                        throw new MediaException('No compatible codecs found');
                    }

                    // 添加媒体行
                    $sdp[] = sprintf(
                        "m=audio %d RTP/AVP %s",
                        $this->allocateRtpPort(),
                        implode(' ', array_column($supportedCodecs, 'payload'))
                    );

                    // 添加连接信息
                    $sdp[] = sprintf("c=IN IP4 %s", $this->config->get('LOCAL_IP'));

                    // 添加编解码器信息
                    foreach ($supportedCodecs as $codec) {
                        $sdp[] = sprintf(
                            "a=rtpmap:%d %s/%d%s",
                            $codec['payload'],
                            $codec['name'],
                            $codec['rate'],
                            isset($codec['channels']) && $codec['channels'] > 1 ? '/' . $codec['channels'] : ''
                        );

                        if (isset($codec['fmtp'])) {
                            $sdp[] = sprintf("a=fmtp:%d %s", $codec['payload'], $codec['fmtp']);
                        }
                    }

                    // 添加其他媒体属性
                    $sdp[] = "a=sendrecv";
                    $sdp[] = "a=rtcp-mux";
                    $sdp[] = "a=maxptime:20";
                    $sdp[] = "a=ptime:20";

                    break;
                }
            }

            return implode("\r\n", $sdp) . "\r\n";
        } catch (\Exception $e) {
            $this->logger->error('Failed to create SDP answer', [
                'error' => $e->getMessage()
            ]);
            throw new MediaException('Failed to create SDP answer: ' . $e->getMessage());
        }
    }

    /**
     * 获取支持的编解码器
     */
    private function getSupportedCodecs($mediaDescription): array
    {
        $supportedCodecs = [];
        $offeredFormats = $mediaDescription->getFormats();
        $rtpMap = $mediaDescription->getRtpMap();

        foreach (self::DEFAULT_AUDIO_CODECS as $codec) {
            foreach ($offeredFormats as $index => $format) {
                if ($format == $codec['payload']) {
                    // 检查 rtpmap 是否匹配
                    if (isset($rtpMap[$index])) {
                        $map = $rtpMap[$index];
                        if (
                            $map['encoding'] === $codec['name'] &&
                            $map['rate'] === $codec['rate'] &&
                            ($map['channels'] ?? 1) === $codec['channels']
                        ) {
                            $supportedCodecs[] = $codec;
                            break;
                        }
                    } else {
                        // 如果没有 rtpmap，使用静态 payload type
                        $supportedCodecs[] = $codec;
                        break;
                    }
                }
            }
        }

        return $supportedCodecs;
    }

    /**
     * 获取 Janus RTP 信息
     */
    public function getJanusRtpInfo(int $roomId): array
    {
        try {
            $this->logger->info('Getting Janus RTP info', [
                'roomId' => $roomId
            ]);

            // 从 Janus 获取房间的 RTP 配置
            $response = $this->janusGateway->getRoomRtpInfo($this->sessionId, $this->handleId, $roomId);

            if (!isset($response['data'])) {
                throw new MediaException('Invalid response from Janus');
            }

            return [
                'ip' => $response['data']['ip'] ?? $this->config->get('JANUS_HOST'),
                'port' => (int)($response['data']['port'] ?? $this->config->get('JANUS_RTP_PORT')),
                'codec' => $response['data']['codec'] ?? 'opus',
                'ptime' => (int)($response['data']['ptime'] ?? 20)
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get Janus RTP info', [
                'error' => $e->getMessage(),
                'roomId' => $roomId
            ]);
            throw new MediaException('Failed to get Janus RTP info: ' . $e->getMessage());
        }
    }

    /**
     * 从 SDP 中获取 RTP 信息
     */
    public function getRtpInfoFromSdp(string $sdp): array
    {
        try {
            $this->logger->debug('Extracting RTP info from SDP', [
                'sdp' => $sdp
            ]);

            $lines = explode("\r\n", $sdp);
            $rtpInfo = [
                'ip' => '',
                'port' => 0,
                'codec' => '',
                'ptime' => 20
            ];

            $currentMedia = null;

            foreach ($lines as $line) {
                // 解析媒体行 (m=audio 49170 RTP/AVP 0)
                if (strpos($line, 'm=audio') === 0) {
                    $parts = explode(' ', $line);
                    $rtpInfo['port'] = (int)$parts[1];
                    $currentMedia = 'audio';
                }
                // 解析连接信息行 (c=IN IP4 224.2.17.12)
                elseif (strpos($line, 'c=IN IP4') === 0) {
                    $parts = explode(' ', $line);
                    $rtpInfo['ip'] = end($parts);
                }
                // 解析编解码器信息 (a=rtpmap:0 PCMU/8000)
                elseif ($currentMedia === 'audio' && strpos($line, 'a=rtpmap:') === 0) {
                    $parts = explode(' ', substr($line, 9));
                    $codecInfo = explode('/', $parts[1]);
                    $rtpInfo['codec'] = $codecInfo[0];
                }
                // 解析打包时间 (a=ptime:20)
                elseif ($currentMedia === 'audio' && strpos($line, 'a=ptime:') === 0) {
                    $rtpInfo['ptime'] = (int)substr($line, 8);
                }
            }

            $this->logger->debug('Extracted RTP info', [
                'rtpInfo' => $rtpInfo
            ]);

            return $rtpInfo;
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract RTP info from SDP', [
                'error' => $e->getMessage()
            ]);
            throw new MediaException('Failed to extract RTP info from SDP: ' . $e->getMessage());
        }
    }

    /**
     * 更新 RTP 转发配置
     */
    public function updateRtpForwarding(string $callId, array $rtpInfo): void
    {
        try {
            $this->logger->info('Updating RTP forwarding', [
                'callId' => $callId,
                'rtpInfo' => $rtpInfo
            ]);

            if (!isset($this->activeMediaSessions[$callId])) {
                throw new MediaException('No active media session found for call ID: ' . $callId);
            }

            $session = $this->activeMediaSessions[$callId];
            if (!isset($session['rtp_forwarding'])) {
                throw new MediaException('No RTP forwarding configuration found for this session');
            }

            // 停止现有的转发
            foreach ($session['rtp_forwarding'] as $direction => $config) {
                $this->stopRtpStream($config);
            }

            // 更新配置
            $asteriskToJanusConfig = [
                'callId' => $callId,
                'sourceIp' => $rtpInfo['local_ip'] ?? $this->config->get('LOCAL_IP'),
                'sourcePort' => $rtpInfo['local_port'] ?? $this->allocateRtpPort(),
                'targetIp' => $rtpInfo['remote_ip'],
                'targetPort' => $rtpInfo['remote_port'],
                'direction' => 'asterisk_to_janus'
            ];

            $janusToAsteriskConfig = [
                'callId' => $callId,
                'sourceIp' => $rtpInfo['remote_ip'],
                'sourcePort' => $rtpInfo['remote_port'],
                'targetIp' => $rtpInfo['local_ip'] ?? $this->config->get('LOCAL_IP'),
                'targetPort' => $rtpInfo['local_port'] ?? $this->allocateRtpPort(),
                'direction' => 'janus_to_asterisk'
            ];

            // 启动新的转发
            $this->startRtpStream($asteriskToJanusConfig);
            $this->startRtpStream($janusToAsteriskConfig);

            // 更新会话配置
            $this->activeMediaSessions[$callId]['rtp_forwarding'] = [
                'asterisk_to_janus' => $asteriskToJanusConfig,
                'janus_to_asterisk' => $janusToAsteriskConfig
            ];

            $this->logger->info('Updated RTP forwarding configuration', [
                'callId' => $callId,
                'config' => $this->activeMediaSessions[$callId]['rtp_forwarding']
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update RTP forwarding', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);
            throw new MediaException('Failed to update RTP forwarding: ' . $e->getMessage());
        }
    }
}
