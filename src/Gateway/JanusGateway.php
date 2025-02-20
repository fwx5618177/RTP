<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Config\Config;
use App\Exceptions\GatewayException;
use App\Logs\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use App\Exceptions\JanusException;

class JanusGateway
{
    private Logger $logger;
    private Config $config;
    private string $baseUrl;
    private string $apiSecret;
    private Client $client;

    // 插件常量
    public const PLUGIN_AUDIOBRIDGE = 'janus.plugin.audiobridge';
    public const PLUGIN_SIP = 'janus.plugin.sip';

    public function __construct()
    {
        $this->logger = Logger::getInstance('janus-gateway');
        $this->config = Config::getInstance();
        $this->baseUrl = $this->config->get('JANUS_HTTP_ENDPOINT', 'http://127.0.0.1:8088/janus');
        $this->apiSecret = $this->config->get('JANUS_API_SECRET', 'janusrocks');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => 10.0,
            'connect_timeout' => 5.0,
            'http_errors' => false,
            'verify' => false,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * 发送请求到 Janus 服务器
     *
     * @param string $sessionId 会话ID
     * @param string|null $handleId 处理器ID
     * @param array $data 请求数据
     * @return array 响应数据
     * @throws JanusException
     */
    public function sendRequest(?string $sessionId, ?string $handleId, array $data): array
    {
        try {
            // 构建 URL
            $url = rtrim($this->config->get('JANUS_API_URL'), '/');
            if ($sessionId) {
                $url .= '/' . $sessionId;
                if ($handleId) {
                    $url .= '/' . $handleId;
                }
            }

            // 添加 API Secret
            $data['apisecret'] = $this->apiSecret;

            // 如果没有 transaction，添加一个
            if (!isset($data['transaction'])) {
                $data['transaction'] = $this->generateTransactionId();
            }

            $this->logger->debug("Sending request to Janus", [
                'url' => $url,
                'data' => $data
            ]);

            // 对于 trickle 请求使用较短的超时时间
            $options = [
                'json' => $data,
            ];

            if ($handleId && str_contains($url, '/trickle')) {
                $options['timeout'] = 2.0; // trickle 请求使用更短的超时时间
            }

            $response = $this->client->post($url, $options);

            // 检查状态码
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new JanusException("Janus returned non-200 status code: $statusCode");
            }

            $contents = $response->getBody()->getContents();

            // 特殊处理 trickle 请求
            if ($handleId && str_contains($url, '/trickle')) {
                // 如果是空响应或者响应体很小，都认为是正常的
                if (empty($contents) || strlen($contents) < 5) {
                    return [
                        'janus' => 'ack',
                        'transaction' => $data['transaction']
                    ];
                }
            }

            $result = json_decode($contents, true);
            if (!is_array($result)) {
                throw new JanusException('Invalid JSON response from Janus');
            }

            return $result;
        } catch (GuzzleException $e) {
            throw new JanusException('Failed to send request to Janus: ' . $e->getMessage());
        }
    }

    /**
     * 创建 Janus 会话
     */
    public function createSession(): array
    {
        try {
            $request = [
                'janus' => 'create',
            ];

            return $this->sendRequest(null, null, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to create session: ' . $e->getMessage());
        }
    }

    /**
     * 附加到插件
     */
    public function attachPlugin(string $sessionId): array
    {
        try {
            $request = [
                'janus' => 'attach',
                'plugin' => 'janus.plugin.audiobridge'
            ];

            return $this->sendRequest($sessionId, null, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to attach plugin: ' . $e->getMessage());
        }
    }

    /**
     * 创建音频房间
     */
    public function createAudioRoom(string $sessionId, string $handleId, array $roomConfig): array
    {
        try {
            $request = [
                'janus' => 'message',
                'body' => [
                    'request' => 'create',
                    'room' => $roomConfig['roomId'],
                    'description' => $roomConfig['description'],
                    'sampling_rate' => $roomConfig['sampling_rate'],
                    'spatial_audio' => $roomConfig['spatial_audio'],
                    'record' => $roomConfig['record'],
                    'notify_joining' => $roomConfig['notify_joining']
                ]
            ];

            return $this->sendRequest($sessionId, $handleId, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to create audio room: ' . $e->getMessage());
        }
    }

    /**
     * 销毁音频房间
     */
    public function destroyAudioRoom(string $sessionId, string $handleId, int $roomId): array
    {
        try {
            $request = [
                'janus' => 'message',
                'body' => [
                    'request' => 'destroy',
                    'room' => $roomId
                ]
            ];

            return $this->sendRequest($sessionId, $handleId, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to destroy audio room: ' . $e->getMessage());
        }
    }

    /**
     * 加入音频房间
     */
    public function joinAudioRoom(string $sessionId, string $handleId, int $roomId, string $display): array
    {
        try {
            $request = [
                'janus' => 'message',
                'body' => [
                    'request' => 'join',
                    'room' => $roomId,
                    'display' => $display,
                    'muted' => false
                ]
            ];

            return $this->sendRequest($sessionId, $handleId, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to join audio room: ' . $e->getMessage());
        }
    }

    /**
     * 离开音频房间
     */
    public function leaveAudioRoom(string $sessionId, string $handleId, int $roomId): array
    {
        try {
            $request = [
                'janus' => 'message',
                'body' => [
                    'request' => 'leave',
                    'room' => $roomId
                ]
            ];

            return $this->sendRequest($sessionId, $handleId, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to leave audio room: ' . $e->getMessage());
        }
    }

    /**
     * 列出房间参与者
     */
    public function listParticipants(string $sessionId, string $handleId, int $roomId): array
    {
        try {
            $request = [
                'janus' => 'message',
                'body' => [
                    'request' => 'listparticipants',
                    'room' => $roomId
                ]
            ];

            return $this->sendRequest($sessionId, $handleId, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to list participants: ' . $e->getMessage());
        }
    }

    /**
     * 发送 ICE Trickle
     */
    public function sendTrickle(string $sessionId, string $handleId, array $candidate): array
    {
        try {
            $request = [
                'janus' => 'trickle',
                'candidate' => $candidate
            ];

            return $this->sendRequest($sessionId, $handleId, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to send trickle: ' . $e->getMessage());
        }
    }

    /**
     * 发送 ICE Trickle Complete
     */
    public function sendTrickleComplete(string $sessionId, string $handleId): array
    {
        try {
            $request = [
                'janus' => 'trickle',
                'candidate' => [
                    'completed' => true
                ]
            ];

            return $this->sendRequest($sessionId, $handleId, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to send trickle complete: ' . $e->getMessage());
        }
    }

    /**
     * 列出所有句柄
     */
    public function listHandles(string $sessionId): array
    {
        try {
            $request = [
                'janus' => 'list_handles'
            ];

            return $this->sendRequest($sessionId, null, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to list handles: ' . $e->getMessage());
        }
    }

    /**
     * 获取句柄信息
     */
    public function handleInfo(string $sessionId, string $handleId): array
    {
        try {
            $request = [
                'janus' => 'handle_info'
            ];

            return $this->sendRequest($sessionId, $handleId, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to get handle info: ' . $e->getMessage());
        }
    }

    /**
     * 销毁会话
     */
    public function destroySession(string $sessionId): array
    {
        try {
            $request = [
                'janus' => 'destroy'
            ];

            return $this->sendRequest($sessionId, null, $request);
        } catch (\Exception $e) {
            throw new JanusException('Failed to destroy session: ' . $e->getMessage());
        }
    }

    /**
     * 创建 SIP 会话并连接到音频房间
     */
    public function createSipBridgeSession(string $sessionId, string $handleId, array $config): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'bridge',
                'room' => (int)$config['roomId'],
                'sip' => [
                    'uri' => $config['uri'],
                    'call_id' => $config['call_id'] ?? uniqid('call_'),
                    'headers' => $config['headers'] ?? [],
                    'srtp' => 'sdes_optional'
                ],
                'muted' => $config['muted'] ?? false,
                'quality' => $config['quality'] ?? 4,
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 更新 SIP 桥接会话
     */
    public function updateSipBridge(string $sessionId, string $handleId, array $config): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'update_bridge',
                'muted' => $config['muted'] ?? false,
                'quality' => $config['quality'] ?? 4,
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 断开 SIP 桥接会话
     */
    public function disconnectSipBridge(string $sessionId, string $handleId): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'disconnect'
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 注册 SIP 账号
     */
    public function registerSip(string $sessionId, string $handleId, array $config): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'register',
                'username' => $config['username'],
                'display_name' => $config['display_name'] ?? '',
                'authuser' => $config['authuser'] ?? $config['username'],
                'secret' => $config['secret'],
                'proxy' => $config['proxy'],
                'sips' => false,
                'refresh' => true
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 发起 SIP 呼叫
     */
    public function makeCall(string $sessionId, string $handleId, array $config): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'call',
                'uri' => $config['uri'],
                'call_id' => $config['call_id'] ?? uniqid('call_'),
                'headers' => $config['headers'] ?? [],
                'srtp' => 'sdes_optional'
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 接受 SIP 呼叫
     */
    public function acceptCall(string $sessionId, string $handleId): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'accept'
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 挂断 SIP 呼叫
     */
    public function hangupCall(string $sessionId, string $handleId): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'hangup'
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 发送 DTMF
     */
    public function sendDtmf(string $sessionId, string $handleId, string $digit): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'dtmf_info',
                'digit' => $digit
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 获取 SIP 插件状态
     */
    public function getSipPluginStatus(string $sessionId, string $handleId): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'status'
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 发送 SIP INFO 消息
     */
    public function sendSipInfo(string $sessionId, string $handleId, array $info): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'info',
                'type' => $info['type'] ?? 'application/dtmf',
                'content' => $info['content'] ?? '',
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 更新 SIP 呼叫媒体设置
     */
    public function updateCallMedia(string $sessionId, string $handleId, array $media): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'update',
                'audio' => $media['audio'] ?? true,
                'video' => $media['video'] ?? false,
                'data' => $media['data'] ?? false,
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 获取 SIP 呼叫统计信息
     */
    public function getCallStats(string $sessionId, string $handleId): array
    {
        $request = [
            'janus' => 'message',
            'body' => [
                'request' => 'callstats'
            ],
            'transaction' => $this->generateTransactionId()
        ];

        return $this->sendRequest($sessionId, $handleId, $request);
    }

    /**
     * 处理 SIP 事件
     */
    public function handleSipEvent(array $event): array
    {
        $this->logger->debug('Handling SIP event', ['event' => $event]);

        $result = [
            'type' => $event['janus'] ?? 'unknown',
            'session_id' => $event['session_id'] ?? null,
            'handle_id' => $event['handle_id'] ?? null,
            'status' => 'unknown'
        ];

        if (isset($event['plugindata']['data'])) {
            $data = $event['plugindata']['data'];
            $result['sip'] = [
                'event' => $data['event'] ?? 'unknown',
                'result' => $data['result'] ?? null,
                'call_id' => $data['call-id'] ?? null,
                'code' => $data['code'] ?? null,
                'reason' => $data['reason'] ?? null
            ];

            // 处理特定的 SIP 事件
            switch ($data['event'] ?? '') {
                case 'registered':
                    $result['status'] = 'registered';
                    break;
                case 'registration_failed':
                    $result['status'] = 'registration_failed';
                    break;
                case 'incoming_call':
                    $result['status'] = 'incoming';
                    break;
                case 'accepting':
                    $result['status'] = 'accepting';
                    break;
                case 'progress':
                    $result['status'] = 'progress';
                    break;
                case 'accepted':
                    $result['status'] = 'connected';
                    break;
                case 'hangup':
                    $result['status'] = 'hangup';
                    break;
                default:
                    $result['status'] = 'unknown';
            }
        }

        return $result;
    }

    /**
     * 生成事务ID
     */
    private function generateTransactionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * 获取房间的 RTP 信息
     */
    public function getRoomRtpInfo(string $sessionId, string $handleId, int $roomId): array
    {
        try {
            $this->logger->info('Getting room RTP info', [
                'sessionId' => $sessionId,
                'handleId' => $handleId,
                'roomId' => $roomId
            ]);

            // 1. 先获取房间信息
            $roomRequest = [
                'janus' => 'message',
                'transaction' => $this->generateTransactionId(),
                'body' => [
                    'request' => 'get_room',
                    'room' => $roomId
                ]
            ];
            $roomResponse = $this->sendRequest($sessionId, $handleId, $roomRequest);

            if (!isset($roomResponse['plugindata']['data']['room'])) {
                throw new JanusException('Room not found');
            }

            // 2. 获取房间的 RTP 配置
            $rtpRequest = [
                'janus' => 'message',
                'transaction' => $this->generateTransactionId(),
                'body' => [
                    'request' => 'rtp_forward',
                    'room' => $roomId,
                    'publisher_id' => 0,  // 0 表示获取房间默认配置
                    'audio' => true,
                    'video' => false,
                    'data' => false
                ]
            ];
            $rtpResponse = $this->sendRequest($sessionId, $handleId, $rtpRequest);

            // 3. 解析 RTP 配置
            $rtpInfo = [
                'data' => [
                    'ip' => $this->config->get('JANUS_HOST'),
                    'port' => (int)$this->config->get('JANUS_RTP_PORT'),
                    'codec' => 'opus',
                    'ptime' => 20,
                    'room' => $roomId
                ]
            ];

            // 如果有具体的 RTP 配置，使用返回的配置
            if (isset($rtpResponse['plugindata']['data']['rtp'])) {
                $rtp = $rtpResponse['plugindata']['data']['rtp'];
                $rtpInfo['data'] = array_merge($rtpInfo['data'], [
                    'ip' => $rtp['ip'] ?? $rtpInfo['data']['ip'],
                    'port' => (int)($rtp['port'] ?? $rtpInfo['data']['port']),
                    'codec' => $rtp['codec'] ?? $rtpInfo['data']['codec'],
                    'ptime' => (int)($rtp['ptime'] ?? $rtpInfo['data']['ptime'])
                ]);
            }

            $this->logger->debug('Got room RTP info', [
                'roomId' => $roomId,
                'rtpInfo' => $rtpInfo
            ]);

            return $rtpInfo;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get room RTP info', [
                'error' => $e->getMessage(),
                'roomId' => $roomId
            ]);
            throw new JanusException('Failed to get room RTP info: ' . $e->getMessage());
        }
    }
}
