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
    ];

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
}
