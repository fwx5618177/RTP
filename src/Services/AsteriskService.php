<?php

namespace App\Services;

use Exception;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Action\SipShowPeerAction;
use PAMI\Message\Action\CoreShowChannelsAction;
use PAMI\Message\Action\StatusAction;
use App\Logs\Logger;
use App\Exceptions\MediaException;

class AsteriskService
{
    private $client;
    private $options;
    private Logger $logger;
    private array $activeChannels = [];

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

            // 检查分机是否已经在通话中
            if ($this->isExtensionBusy($extension)) {
                throw new Exception("Extension $extension is busy");
            }

            // 创建呼叫动作
            $channel = 'SIP/' . $extension;
            $action = new OriginateAction($channel);
            $action->setContext('default');
            $action->setExtension('9' . $roomId); // 使用9前缀表示转发到Janus
            $action->setPriority('1');
            $action->setAsync(true);
            $action->setCallerId('WebRTC-Room-' . $roomId);
            $action->setTimeout(30000); // 30秒超时

            // 设置 SIP 头
            $action->setVariable('__SIPADDHEADER01', 'X-Room-Number: ' . $roomId);
            $action->setVariable('__SIPADDHEADER02', 'X-Janus-Room: ' . $roomId);
            $action->setVariable('__SIPADDHEADER03', 'X-Source: asterisk');
            $action->setVariable('__SIPADDHEADER04', 'X-Conference-Room: ' . $roomId);
            $action->setVariable('__SIPADDHEADER05', 'X-Conference-Server: rtp-bridge-janus');

            // 设置 RTP 相关变量
            $action->setVariable('RTPSTART', 'yes');
            $action->setVariable('RTPQOS', 'yes');
            $action->setVariable('RTPAUDIOQOS', 'yes');
            $action->setVariable('RTPAUTOADJUST', 'yes');

            // 设置编解码器优先级
            $action->setVariable('CODEC1', 'opus');
            $action->setVariable('CODEC2', 'g722');
            $action->setVariable('CODEC3', 'alaw');
            $action->setVariable('CODEC4', 'ulaw');

            $response = $this->client->send($action);
            $actionId = $response->getActionId();

            // 记录活动通道
            $this->activeChannels[$extension] = [
                'channel' => $channel,
                'roomId' => $roomId,
                'actionId' => $actionId,
                'startTime' => time(),
                'status' => 'initiated'
            ];

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
                'response' => $response->getKeys(),
                'channel' => $channel
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
     * 检查分机是否正在通话中
     */
    private function isExtensionBusy(string $extension): bool
    {
        try {
            $action = new CoreShowChannelsAction();
            $response = $this->client->send($action);
            $channels = $response->getKeys();

            foreach ($channels as $channel) {
                if (strpos($channel['Channel'] ?? '', 'SIP/' . $extension) === 0) {
                    return true;
                }
            }

            return false;
        } catch (Exception $e) {
            $this->logger->error('Failed to check extension status', [
                'error' => $e->getMessage(),
                'extension' => $extension
            ]);
            return false;
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
            $status = $response->getKeys();

            // 解析通道状态
            $callStatus = [
                'channel' => $channel,
                'status' => $status['Status'] ?? 'unknown',
                'duration' => $status['Duration'] ?? '0',
                'state' => $status['State'] ?? 'unknown',
                'callerid' => $status['CallerID'] ?? '',
                'connected_line' => $status['ConnectedLine'] ?? '',
                'application' => $status['Application'] ?? '',
                'application_data' => $status['ApplicationData'] ?? '',
                'variables' => []
            ];

            // 添加 RTP 统计信息
            if (isset($status['RTCPSent'])) {
                $callStatus['rtp_stats'] = [
                    'rtcp_sent' => $status['RTCPSent'],
                    'rtcp_received' => $status['RTCPReceived'],
                    'rtp_packets_sent' => $status['RTPPacketsSent'],
                    'rtp_packets_received' => $status['RTPPacketsReceived'],
                    'rtp_lost_packets' => $status['RTPLostPackets'],
                    'rtp_jitter' => $status['RTPJitter']
                ];
            }

            return $callStatus;
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

    /**
     * 从通道获取 RTP 信息
     */
    public function getRtpInfo(string $channel): array
    {
        try {
            $this->connect();

            $action = new StatusAction($channel);
            $response = $this->client->send($action);
            $status = $response->getKeys();

            // 获取 RTP 相关信息
            $rtpInfo = [
                'ip' => $status['RemoteAddress'] ?? '',
                'port' => (int)($status['RemotePort'] ?? 0),
                'localIp' => $status['LocalAddress'] ?? '',
                'localPort' => (int)($status['LocalPort'] ?? 0),
                'codec' => $status['WriteFormat'] ?? 'unknown',
                'stats' => [
                    'packets_sent' => (int)($status['RTPPacketsSent'] ?? 0),
                    'packets_received' => (int)($status['RTPPacketsReceived'] ?? 0),
                    'lost_packets' => (int)($status['RTPLostPackets'] ?? 0),
                    'jitter' => (float)($status['RTPJitter'] ?? 0.0),
                    'round_trip_time' => (float)($status['RTPRoundTripTime'] ?? 0.0)
                ]
            ];

            return $rtpInfo;
        } catch (Exception $e) {
            $this->logger->error('Failed to get RTP info', [
                'error' => $e->getMessage(),
                'channel' => $channel
            ]);
            throw new Exception("Failed to get RTP info: " . $e->getMessage());
        } finally {
            if ($this->client) {
                $this->client->close();
                $this->client = null;
            }
        }
    }

    /**
     * 从 SDP 中提取 RTP 信息
     */
    public function getRtpInfoFromSdp(string $sdp): array
    {
        try {
            $lines = explode("\r\n", $sdp);
            $rtpInfo = [
                'ip' => '',
                'port' => 0,
                'codec' => '',
                'ptime' => 20
            ];

            foreach ($lines as $line) {
                // 解析连接信息 (c=IN IP4 xxx.xxx.xxx.xxx)
                if (strpos($line, 'c=IN IP4') === 0) {
                    $rtpInfo['ip'] = trim(substr($line, 9));
                }
                // 解析媒体行 (m=audio xxxx RTP/AVP xx)
                elseif (strpos($line, 'm=audio') === 0) {
                    $parts = explode(' ', $line);
                    $rtpInfo['port'] = (int)$parts[1];
                    $rtpInfo['payloads'] = array_slice($parts, 3);
                }
                // 解析编解码器信息 (a=rtpmap:xx PCMA/8000)
                elseif (strpos($line, 'a=rtpmap:') === 0) {
                    $parts = explode(' ', substr($line, 9));
                    $codecInfo = explode('/', $parts[1]);
                    $rtpInfo['codec'] = $codecInfo[0];
                    $rtpInfo['rate'] = (int)$codecInfo[1];
                }
                // 解析打包时间 (a=ptime:xx)
                elseif (strpos($line, 'a=ptime:') === 0) {
                    $rtpInfo['ptime'] = (int)substr($line, 8);
                }
            }

            return $rtpInfo;
        } catch (Exception $e) {
            $this->logger->error('Failed to parse RTP info from SDP', [
                'error' => $e->getMessage(),
                'sdp' => $sdp
            ]);
            throw new Exception("Failed to parse RTP info from SDP: " . $e->getMessage());
        }
    }

    /**
     * 处理入站 SIP 呼叫
     */
    public function handleInboundCall(string $extension, array $sipHeaders = []): array
    {
        try {
            $this->connect();
            $this->logger->info('Handling inbound call', [
                'extension' => $extension,
                'headers' => $sipHeaders
            ]);

            // 检查分机状态
            if ($this->isExtensionBusy($extension)) {
                throw new MediaException("Extension $extension is busy");
            }

            // 生成唯一呼叫ID
            $callId = uniqid('call_', true);

            // 创建通道变量
            $channelVars = [
                'SIPCALLID' => $callId,
                'RTPAUDIOQOS' => 'yes',
                'RTPAUDIOQOSINT' => '5',
                'RTPSTART' => 'yes',
                'RTPQOSINT' => '5'
            ];

            // 添加自定义 SIP 头
            foreach ($sipHeaders as $key => $value) {
                $channelVars["SIP_HEADER($key)"] = $value;
            }

            // 记录呼叫状态
            $this->activeChannels[$extension] = [
                'callId' => $callId,
                'channel' => "SIP/$extension",
                'status' => 'ringing',
                'startTime' => time(),
                'channelVars' => $channelVars
            ];

            return [
                'callId' => $callId,
                'extension' => $extension,
                'status' => 'ringing',
                'channelVars' => $channelVars
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to handle inbound call', [
                'error' => $e->getMessage(),
                'extension' => $extension
            ]);
            throw $e;
        }
    }

    /**
     * 处理 SIP 响应
     */
    public function handleSipResponse(string $callId, int $statusCode, array $headers = []): array
    {
        try {
            $this->connect();
            $this->logger->info('Handling SIP response', [
                'callId' => $callId,
                'statusCode' => $statusCode,
                'headers' => $headers
            ]);

            // 查找相关通道
            $channel = $this->findChannelByCallId($callId);
            if (!$channel) {
                throw new MediaException("No channel found for call ID: $callId");
            }

            // 更新通道状态
            switch ($statusCode) {
                case 180: // Ringing
                    $channel['status'] = 'ringing';
                    break;
                case 200: // OK
                    $channel['status'] = 'answered';
                    $channel['answerTime'] = time();
                    break;
                case 486: // Busy Here
                case 600: // Busy Everywhere
                    $channel['status'] = 'busy';
                    break;
                case 487: // Request Terminated
                    $channel['status'] = 'cancelled';
                    break;
                default:
                    if ($statusCode >= 400) {
                        $channel['status'] = 'failed';
                        $channel['failureCode'] = $statusCode;
                    }
            }

            // 更新通道信息
            $this->updateChannelInfo($channel);

            return [
                'callId' => $callId,
                'status' => $channel['status'],
                'statusCode' => $statusCode,
                'channel' => $channel
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to handle SIP response', [
                'error' => $e->getMessage(),
                'callId' => $callId,
                'statusCode' => $statusCode
            ]);
            throw $e;
        }
    }

    /**
     * 处理 SIP BYE 请求
     */
    public function handleSipBye(string $callId, array $headers = []): array
    {
        try {
            $this->connect();
            $this->logger->info('Handling SIP BYE', [
                'callId' => $callId,
                'headers' => $headers
            ]);

            // 查找并更新通道状态
            $channel = $this->findChannelByCallId($callId);
            if ($channel) {
                $channel['status'] = 'terminated';
                $channel['endTime'] = time();
                $channel['duration'] = $channel['endTime'] - $channel['startTime'];

                // 更新通道信息
                $this->updateChannelInfo($channel);

                // 获取最终的 RTP 统计
                $rtpStats = $this->getRtpStats($channel['channel']);

                return [
                    'callId' => $callId,
                    'status' => 'terminated',
                    'duration' => $channel['duration'],
                    'rtpStats' => $rtpStats
                ];
            }

            throw new MediaException("No channel found for call ID: $callId");
        } catch (Exception $e) {
            $this->logger->error('Failed to handle SIP BYE', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);
            throw $e;
        }
    }

    /**
     * 获取 RTP 统计信息
     */
    private function getRtpStats(string $channel): array
    {
        try {
            $action = new StatusAction($channel);
            $response = $this->client->send($action);
            $status = $response->getKeys();

            return [
                'packets_sent' => (int)($status['RTPPacketsSent'] ?? 0),
                'packets_received' => (int)($status['RTPPacketsReceived'] ?? 0),
                'packets_lost' => (int)($status['RTPLostPackets'] ?? 0),
                'jitter' => (float)($status['RTPJitter'] ?? 0.0),
                'round_trip_time' => (float)($status['RTPRoundTripTime'] ?? 0.0),
                'mos' => (float)($status['RTPMos'] ?? 0.0)
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get RTP stats', [
                'error' => $e->getMessage(),
                'channel' => $channel
            ]);
            return [];
        }
    }

    /**
     * 根据呼叫ID查找通道
     */
    private function findChannelByCallId(string $callId): ?array
    {
        foreach ($this->activeChannels as $channel) {
            if ($channel['callId'] === $callId) {
                return $channel;
            }
        }
        return null;
    }

    /**
     * 更新通道信息
     */
    private function updateChannelInfo(array $channel): void
    {
        if (isset($channel['extension'])) {
            $this->activeChannels[$channel['extension']] = $channel;
        }
    }
}
