<?php

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 创建 UDP socket
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
if (!$socket) {
    die("Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
}

// 设置更长的超时时间 (10秒)
socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 10, 'usec' => 0));
socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 10, 'usec' => 0));

// 设置更大的缓冲区
socket_set_option($socket, SOL_SOCKET, SO_RCVBUF, 65535);
socket_set_option($socket, SOL_SOCKET, SO_SNDBUF, 65535);

// 允许地址重用
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

// 绑定到特定的本地地址和端口
$local_ip = '0.0.0.0';
$local_port = 0; // 让系统分配端口

if (!socket_bind($socket, $local_ip, $local_port)) {
    die("Failed to bind socket: " . socket_strerror(socket_last_error($socket)) . "\n");
}

// 获取本地绑定的端口
socket_getsockname($socket, $local_ip, $local_port);
echo "Local socket bound to $local_ip:$local_port\n";

// 获取容器名称和网络信息
$container_name = 'rtp-bridge-janus';
echo "Looking for container: $container_name\n";

// 使用 Docker CLI 获取容器 IP
$container_ip = trim(shell_exec("docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $container_name"));
if (empty($container_ip)) {
    die("Failed to get container IP address\n");
}

echo "Container IP: $container_ip\n";

// Janus 服务器地址
$janus_ip = $container_ip;  // 使用容器实际 IP
$janus_port = 5060;        // SIP 端口

// 尝试 ping Janus 服务器
echo "Attempting to ping $janus_ip...\n";
exec("ping -c 1 $janus_ip", $ping_output, $ping_result);
echo "Ping result: " . ($ping_result === 0 ? "Success" : "Failed") . "\n";
echo "Ping output: " . implode("\n", $ping_output) . "\n\n";

// 构造一个简单的 SIP OPTIONS 请求
$branch = 'z9hG4bK' . rand(1000000, 9999999);
$tag = rand(1000000, 9999999);
$call_id = rand(1000000, 9999999);

$sip_request = "OPTIONS sip:janus@$janus_ip:$janus_port SIP/2.0\r\n"
    . "Via: SIP/2.0/UDP $local_ip:$local_port;branch=$branch\r\n"
    . "From: <sip:test@$local_ip:$local_port>;tag=$tag\r\n"
    . "To: <sip:janus@$janus_ip:$janus_port>\r\n"
    . "Call-ID: {$call_id}@$local_ip\r\n"
    . "CSeq: 1 OPTIONS\r\n"
    . "Contact: <sip:test@$local_ip:$local_port>\r\n"
    . "Max-Forwards: 70\r\n"
    . "User-Agent: PHP SIP Test\r\n"
    . "Accept: application/sdp\r\n"
    . "Content-Length: 0\r\n\r\n";

echo "Sending SIP OPTIONS request to $janus_ip:$janus_port...\n";
echo "Request:\n$sip_request\n";

// 发送请求
$result = socket_sendto($socket, $sip_request, strlen($sip_request), 0, $janus_ip, $janus_port);
if ($result === false) {
    die("Failed to send request: " . socket_strerror(socket_last_error($socket)) . "\n");
}

echo "Sent $result bytes\n";
echo "Waiting for response...\n";

// 接收响应
$response = '';
$from = '';
$port = 0;

// 循环尝试接收几次
for ($i = 0; $i < 3; $i++) {
    echo "Attempt " . ($i + 1) . " to receive response...\n";
    $result = socket_recvfrom($socket, $response, 65535, 0, $from, $port);

    if ($result === false) {
        $error_code = socket_last_error($socket);
        echo "Receive failed: " . socket_strerror($error_code) . " (Error code: $error_code)\n";

        if ($i < 2) {
            echo "Waiting 2 seconds before next attempt...\n";
            sleep(2);
            continue;
        }
    } else {
        echo "\nReceived response from $from:$port\n";
        echo "Response:\n$response\n";
        break;
    }
}

// 关闭 socket
socket_close($socket);

// 显示 Docker 容器信息
echo "\nDocker Container Info:\n";
echo "--------------------\n";
