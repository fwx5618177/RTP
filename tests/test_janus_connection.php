<?php

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 获取本机IP地址
$host_ip = '127.0.0.1';
echo "Host IP: $host_ip\n";

// Janus HTTP API 端点和认证信息
$janus_http_endpoint = 'http://127.0.0.1:8088/janus';
$janus_api_secret = 'janusrocks';

try {
    echo "\n=== 创建 Janus 会话 ===\n";
    $createSession = [
        "janus" => "create",
        "transaction" => generateTransactionId(),
        "apisecret" => $janus_api_secret
    ];

    $response = sendRequest($janus_http_endpoint, $createSession);
    if (!isset($response['data']['id'])) {
        throw new Exception("Failed to create Janus session: " . json_encode($response));
    }

    $sessionId = $response['data']['id'];
    echo "Created Janus session: $sessionId\n";

    echo "\n=== 附加到 AudioBridge 插件 ===\n";
    $attachPlugin = [
        "janus" => "attach",
        "plugin" => "janus.plugin.audiobridge",
        "transaction" => generateTransactionId(),
        "apisecret" => $janus_api_secret
    ];

    $response = sendRequest("$janus_http_endpoint/$sessionId", $attachPlugin);
    if (!isset($response['data']['id'])) {
        throw new Exception("Failed to attach to AudioBridge plugin: " . json_encode($response));
    }

    $handleId = $response['data']['id'];
    echo "Attached to AudioBridge plugin: $handleId\n";

    // 创建音频房间
    echo "\n=== 创建音频房间 ===\n";
    $roomId = rand(1000000, 9999999);
    $createRoom = [
        "janus" => "message",
        "body" => [
            "request" => "create",
            "room" => $roomId,
            "description" => "Test Audio Room",
            "secret" => "roomsecret",
            "sampling_rate" => 16000,
            "spatial_audio" => false,
            "record" => false,
            "permanent" => false
        ],
        "transaction" => generateTransactionId(),
        "apisecret" => $janus_api_secret
    ];

    $response = sendRequest("$janus_http_endpoint/$sessionId/$handleId", $createRoom);
    echo "Create room response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

    // 模拟第一个参与者（创建者）加入房间
    echo "\n=== 参与者1加入房间 ===\n";
    $participant1 = [
        "janus" => "message",
        "body" => [
            "request" => "join",
            "room" => $roomId,
            "display" => "Participant 1",
            "muted" => false
        ],
        "transaction" => generateTransactionId(),
        "apisecret" => $janus_api_secret
    ];

    $response = sendRequest("$janus_http_endpoint/$sessionId/$handleId", $participant1);
    echo "Participant 1 join response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

    // 为第二个参与者创建新的句柄
    echo "\n=== 创建参与者2的句柄 ===\n";
    $response = sendRequest("$janus_http_endpoint/$sessionId", $attachPlugin);
    $handleId2 = $response['data']['id'];
    echo "Created handle for participant 2: $handleId2\n";

    // 第二个参与者加入房间
    echo "\n=== 参与者2加入房间 ===\n";
    $participant2 = [
        "janus" => "message",
        "body" => [
            "request" => "join",
            "room" => $roomId,
            "display" => "Participant 2",
            "muted" => false
        ],
        "transaction" => generateTransactionId(),
        "apisecret" => $janus_api_secret
    ];

    $response = sendRequest("$janus_http_endpoint/$sessionId/$handleId2", $participant2);
    echo "Participant 2 join response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

    // 模拟音频配置
    echo "\n=== 配置参与者1的音频 ===\n";
    $participant1Audio = [
        "janus" => "message",
        "body" => [
            "request" => "configure",
            "muted" => false,
            "quality" => 1.0
        ],
        "transaction" => generateTransactionId(),
        "apisecret" => $janus_api_secret
    ];

    $response = sendRequest("$janus_http_endpoint/$sessionId/$handleId", $participant1Audio);
    echo "Participant 1 audio config response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

    // 列出房间参与者
    echo "\n=== 获取房间参与者列表 ===\n";
    $listParticipants = [
        "janus" => "message",
        "body" => [
            "request" => "listparticipants",
            "room" => $roomId
        ],
        "transaction" => generateTransactionId(),
        "apisecret" => $janus_api_secret
    ];

    $response = sendRequest("$janus_http_endpoint/$sessionId/$handleId", $listParticipants);
    echo "Room participants: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";

    // 模拟音频会话持续一段时间
    echo "\n=== 音频会话进行中 ===\n";
    sleep(5);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    // 清理资源
    if (isset($handleId2) && isset($sessionId)) {
        echo "\n=== 参与者2离开房间 ===\n";
        $leaveRoom2 = [
            "janus" => "message",
            "body" => [
                "request" => "leave"
            ],
            "transaction" => generateTransactionId(),
            "apisecret" => $janus_api_secret
        ];
        sendRequest("$janus_http_endpoint/$sessionId/$handleId2", $leaveRoom2);
    }

    if (isset($handleId) && isset($sessionId)) {
        echo "\n=== 参与者1离开房间 ===\n";
        $leaveRoom1 = [
            "janus" => "message",
            "body" => [
                "request" => "leave"
            ],
            "transaction" => generateTransactionId(),
            "apisecret" => $janus_api_secret
        ];
        sendRequest("$janus_http_endpoint/$sessionId/$handleId", $leaveRoom1);

        echo "\n=== 销毁房间 ===\n";
        $destroyRoom = [
            "janus" => "message",
            "body" => [
                "request" => "destroy",
                "room" => $roomId,
                "secret" => "roomsecret"
            ],
            "transaction" => generateTransactionId(),
            "apisecret" => $janus_api_secret
        ];
        sendRequest("$janus_http_endpoint/$sessionId/$handleId", $destroyRoom);
    }

    // 清理句柄和会话
    if (isset($handleId2) && isset($sessionId)) {
        $detach2 = [
            "janus" => "detach",
            "transaction" => generateTransactionId(),
            "apisecret" => $janus_api_secret
        ];
        sendRequest("$janus_http_endpoint/$sessionId/$handleId2", $detach2);
    }

    if (isset($handleId) && isset($sessionId)) {
        $detach = [
            "janus" => "detach",
            "transaction" => generateTransactionId(),
            "apisecret" => $janus_api_secret
        ];
        sendRequest("$janus_http_endpoint/$sessionId/$handleId", $detach);
    }

    if (isset($sessionId)) {
        $destroy = [
            "janus" => "destroy",
            "transaction" => generateTransactionId(),
            "apisecret" => $janus_api_secret
        ];
        sendRequest("$janus_http_endpoint/$sessionId", $destroy);
    }
}

function generateTransactionId()
{
    return "txid" . rand(1000000, 9999999);
}

function sendRequest($url, $data = [], $method = 'POST')
{
    $ch = curl_init($url);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }

    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("HTTP error $httpCode: $response");
    }

    $decoded = json_decode($response, true);
    if ($decoded === null) {
        throw new Exception("Invalid JSON response: $response");
    }

    return $decoded;
}
