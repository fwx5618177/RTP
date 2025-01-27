<?php

declare(strict_types=1);

namespace App\Gateway;

use App\Config\Config;
use App\Exceptions\GatewayException;
use App\Logs\Logger;

class JanusGateway
{
    private Logger $logger;
    private Config $config;
    private string $apiEndpoint;
    private string $apiSecret;

    public function __construct()
    {
        $this->logger = Logger::getInstance('janus-gateway');
        $this->config = Config::getInstance();
        $this->apiEndpoint = $this->config->get('JANUS_HTTP_ENDPOINT', 'http://127.0.0.1:8088/janus');
        $this->apiSecret = $this->config->get('JANUS_API_SECRET', 'janusrocks');
    }

    public function createSession(): array
    {
        $createSession = [
            "janus" => "create",
            "transaction" => $this->generateTransactionId(),
            "apisecret" => $this->apiSecret,
        ];

        return $this->sendRequest($this->apiEndpoint, $createSession);
    }

    public function attachPlugin(string $sessionId, string $plugin = 'janus.plugin.audiobridge'): array
    {
        $attachPlugin = [
            "janus" => "attach",
            "plugin" => $plugin,
            "transaction" => $this->generateTransactionId(),
            "apisecret" => $this->apiSecret,
        ];

        return $this->sendRequest("$this->apiEndpoint/$sessionId", $attachPlugin);
    }

    public function createRoom(string $sessionId, string $handleId, array $roomConfig): array
    {
        $createRoom = [
            "janus" => "message",
            "body" => array_merge([
                "request" => "create",
                "sampling_rate" => 16000,
                "spatial_audio" => false,
                "record" => false,
                "permanent" => false,
            ], $roomConfig),
            "transaction" => $this->generateTransactionId(),
            "apisecret" => $this->apiSecret,
        ];

        return $this->sendRequest("$this->apiEndpoint/$sessionId/$handleId", $createRoom);
    }

    public function joinRoom(string $sessionId, string $handleId, int $roomId, string $display): array
    {
        $joinRoom = [
            "janus" => "message",
            "body" => [
                "request" => "join",
                "room" => $roomId,
                "display" => $display,
                "muted" => false,
            ],
            "transaction" => $this->generateTransactionId(),
            "apisecret" => $this->apiSecret,
        ];

        return $this->sendRequest("$this->apiEndpoint/$sessionId/$handleId", $joinRoom);
    }

    private function generateTransactionId(): string
    {
        return "txid" . rand(1000000, 9999999);
    }

    private function sendRequest(string $url, array $data): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new GatewayException('Curl error: ' . curl_error($ch));
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            throw new GatewayException("HTTP error $httpCode: $response");
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new GatewayException("Invalid JSON response: $response");
        }

        return $decoded;
    }
}
