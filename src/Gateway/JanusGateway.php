<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Config\Config;
use App\Exceptions\GatewayException;
use App\Logs\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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
     */
    public function sendRequest(string $endpoint, array $data): array
    {
        try {
            // 添加 API Secret
            $data['apisecret'] = $this->apiSecret;

            // 如果没有 transaction，添加一个
            if (!isset($data['transaction'])) {
                $data['transaction'] = $this->generateTransactionId();
            }

            // 确保 endpoint 以 / 开头
            $endpoint = ltrim($endpoint, '/');
            $url = $endpoint ? "{$this->baseUrl}/{$endpoint}" : $this->baseUrl;

            $this->logger->debug("Sending request to Janus", [
                'url' => $url,
                'data' => $data
            ]);

            // 对于 trickle 请求使用较短的超时时间
            $options = [
                'json' => $data,
            ];

            if (str_contains($endpoint, '/trickle')) {
                $options['timeout'] = 2.0; // trickle 请求使用更短的超时时间
            }

            $response = $this->client->post($url, $options);

            // 检查状态码
            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new GatewayException("Janus returned non-200 status code: $statusCode");
            }

            $contents = $response->getBody()->getContents();

            // 特殊处理 trickle 请求
            if (str_contains($endpoint, '/trickle')) {
                // 如果是空响应或者响应体很小，都认为是正常的
                if (empty($contents) || strlen($contents) < 5) {
                    return [
                        'janus' => 'ack',
                        'transaction' => $data['transaction']
                    ];
                }
            }

            $result = json_decode($contents, true);

            if (!$result && !str_contains($endpoint, '/trickle')) {
                throw new \Exception('Invalid response from Janus server');
            }

            return $result ?: [];
        } catch (\Exception $e) {
            $this->logger->error("Failed to communicate with Janus", [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint,
                'data' => $data
            ]);

            throw new GatewayException("Failed to communicate with Janus: " . $e->getMessage());
        }
    }

    /**
     * 创建 Janus 会话
     */
    public function createSession(): array
    {
        return $this->sendRequest('', [
            'janus' => 'create',
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 附加到插件
     */
    public function attachPlugin(string $sessionId, string $plugin = self::PLUGIN_AUDIOBRIDGE): array
    {
        return $this->sendRequest("$sessionId", [
            'janus' => 'attach',
            'plugin' => $plugin,
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 创建音频房间
     */
    public function createAudioRoom(string $sessionId, string $handleId, array $config): array
    {
        if (empty($config['roomId']) || !is_numeric($config['roomId'])) {
            throw new GatewayException('Room ID must be a positive integer');
        }

        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'create',
                'room' => (int)$config['roomId'],
                'description' => $config['description'] ?? '',
                'sampling_rate' => $config['sampling_rate'] ?? 16000,
                'spatial_audio' => $config['spatial_audio'] ?? false,
                'record' => false,
                'permanent' => false
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 加入音频房间
     */
    public function joinAudioRoom(string $sessionId, string $handleId, int $roomId, string $display): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            "janus" => "message",
            "body" => [
                "request" => "join",
                "room" => $roomId,
                "display" => $display,
                "muted" => false,
            ],
            "transaction" => $this->generateTransactionId()
        ]);
    }

    /**
     * 创建 SIP 会话并连接到音频房间
     */
    public function createSipBridgeSession(string $sessionId, string $handleId, array $config): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
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
        ]);
    }

    /**
     * 更新 SIP 桥接会话
     */
    public function updateSipBridge(string $sessionId, string $handleId, array $config): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'update_bridge',
                'muted' => $config['muted'] ?? false,
                'quality' => $config['quality'] ?? 4,
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 断开 SIP 桥接会话
     */
    public function disconnectSipBridge(string $sessionId, string $handleId): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'disconnect'
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 注册 SIP 账号
     */
    public function registerSip(string $sessionId, string $handleId, array $config): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
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
        ]);
    }

    /**
     * 发起 SIP 呼叫
     */
    public function makeCall(string $sessionId, string $handleId, array $config): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'call',
                'uri' => $config['uri'],
                'call_id' => $config['call_id'] ?? uniqid('call_'),
                'headers' => $config['headers'] ?? [],
                'srtp' => 'sdes_optional'
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 接受 SIP 呼叫
     */
    public function acceptCall(string $sessionId, string $handleId): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'accept'
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 挂断 SIP 呼叫
     */
    public function hangupCall(string $sessionId, string $handleId): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'hangup'
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 发送 DTMF
     */
    public function sendDtmf(string $sessionId, string $handleId, string $digit): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'dtmf_info',
                'digit' => $digit
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 获取房间参与者列表
     */
    public function listParticipants(string $sessionId, string $handleId, string $roomId): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            "janus" => "message",
            "body" => [
                "request" => "listparticipants",
                "room" => (int)$roomId
            ],
            "transaction" => $this->generateTransactionId()
        ]);
    }

    /**
     * 获取 SIP 插件状态
     */
    public function getSipPluginStatus(string $sessionId, string $handleId): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'status'
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 发送 SIP INFO 消息
     */
    public function sendSipInfo(string $sessionId, string $handleId, array $info): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'info',
                'type' => $info['type'] ?? 'application/dtmf',
                'content' => $info['content'] ?? '',
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 更新 SIP 呼叫媒体设置
     */
    public function updateCallMedia(string $sessionId, string $handleId, array $media): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'update',
                'audio' => $media['audio'] ?? true,
                'video' => $media['video'] ?? false,
                'data' => $media['data'] ?? false,
            ],
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 获取 SIP 呼叫统计信息
     */
    public function getCallStats(string $sessionId, string $handleId): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'message',
            'body' => [
                'request' => 'callstats'
            ],
            'transaction' => $this->generateTransactionId()
        ]);
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
     * 获取会话的所有句柄
     */
    public function listHandles(string $sessionId): array
    {
        return $this->sendRequest($sessionId, [
            'janus' => 'list_handles',
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 获取句柄信息
     */
    public function handleInfo(string $sessionId, string $handleId): array
    {
        return $this->sendRequest("$sessionId/$handleId", [
            'janus' => 'handle_info',
            'transaction' => $this->generateTransactionId()
        ]);
    }

    /**
     * 销毁会话
     */
    public function destroySession(string $sessionId): array
    {
        return $this->sendRequest($sessionId, [
            'janus' => 'destroy',
            'transaction' => $this->generateTransactionId()
        ]);
    }
}
