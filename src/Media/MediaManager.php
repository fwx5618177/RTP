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

    /**
     * 默认的音频编解码器配置
     */
    private const DEFAULT_AUDIO_CODECS = [
        ['payload' => 0, 'name' => 'PCMU', 'rate' => 8000, 'channels' => 1],  // G.711 u-law
        ['payload' => 8, 'name' => 'PCMA', 'rate' => 8000, 'channels' => 1],  // G.711 a-law
        ['payload' => 101, 'name' => 'telephone-event', 'rate' => 8000, 'channels' => 1, 'fmtp' => '0-15']  // DTMF
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
                'userId' => $userId
            ]);

            // 创建 SDP Offer
            $sdpOffer = $this->createSdpOffer();
            $this->logger->debug('Created SDP offer', ['sdp' => $sdpOffer]);

            // 通过 Janus Gateway 发送 SIP INVITE
            $sessionInfo = $this->janusGateway->createRoomSession($roomName, $userId, $sdpOffer);
            $this->logger->debug('Received session info', ['info' => $sessionInfo]);

            // 解析 SDP Answer
            $mediaInfo = $this->parseSdpAnswer($sessionInfo['sdpAnswer']);

            return [
                'roomId' => $sessionInfo['roomId'],
                'mediaInfo' => $mediaInfo,
                'ip' => $sessionInfo['ip'],
                'timestamp' => time()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create media session', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
                'error' => $e->getMessage()
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
                        'formats' => array_slice($parts, 3)
                    ];
                } elseif (strpos($line, 'c=') === 0) {
                    // 连接信息行
                    $parts = explode(' ', $line);
                    $mediaInfo['connection'] = [
                        'ip' => end($parts)
                    ];
                }
            }

            return $mediaInfo;
        } catch (\Exception $e) {
            $this->logger->error('Failed to parse SDP answer', [
                'error' => $e->getMessage(),
                'sdpAnswer' => $sdpAnswer
            ]);
            throw new MediaException('Failed to parse SDP answer: ' . $e->getMessage());
        }
    }
}
