<?php

namespace App\Controllers;

use App\Services\AsteriskService;
use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;
use App\Media\MediaManager;

class PbxController extends BaseController
{
    private AsteriskService $asteriskService;
    private Logger $logger;
    private MediaManager $mediaManager;

    public function __construct(AsteriskService $asteriskService)
    {
        $this->asteriskService = $asteriskService;
        $this->logger = Logger::getInstance('pbx-controller');
        $this->mediaManager = new \App\Media\MediaManager();
    }

    /**
     * 发起 SIP 呼叫到 WebRTC 房间
     */
    public function makeCall(Request $request): Response
    {
        $data = $request->getBodyParams();
        $extension = $data['extension'] ?? '';
        $roomId = $data['roomId'] ?? '';

        if (empty($extension) || empty($roomId)) {
            return $this->errorResponse('Extension and room ID are required');
        }

        try {
            $this->logger->info('Making call', [
                'extension' => $extension,
                'roomId' => $roomId
            ]);

            // 1. 通过 Asterisk 发起呼叫
            $callResult = $this->asteriskService->initiateCall($extension, $roomId);

            // 2. 获取 Asterisk 的 RTP 信息
            $asteriskRtpInfo = $this->asteriskService->getRtpInfo($callResult['channel']);

            // 3. 获取 Janus 的 RTP 信息
            $janusRtpInfo = $this->mediaManager->getJanusRtpInfo($roomId);

            // 4. 设置 RTP 转发
            $rtpResult = $this->mediaManager->setupAsteriskToJanusRtp(
                $callResult['actionId'],
                $asteriskRtpInfo,
                $janusRtpInfo
            );

            $this->logger->info('Call and RTP forwarding setup completed', [
                'call' => $callResult,
                'rtp' => $rtpResult
            ]);

            return $this->successResponse([
                'message' => 'Call initiated and RTP forwarding setup successfully',
                'data' => [
                    'call' => $callResult,
                    'rtp' => $rtpResult
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to setup call and RTP forwarding', [
                'error' => $e->getMessage(),
                'extension' => $extension,
                'roomId' => $roomId
            ]);

            return $this->errorResponse('Failed to setup call and RTP forwarding: ' . $e->getMessage());
        }
    }

    /**
     * 获取呼叫状态
     */
    public function getCallStatus(Request $request): Response
    {
        $data = $request->getBodyParams();
        $channel = $data['channel'] ?? '';

        if (empty($channel)) {
            return $this->errorResponse('Channel is required');
        }

        try {
            $this->logger->info('Getting call status', [
                'channel' => $channel
            ]);

            $result = $this->asteriskService->getCallStatus($channel);

            return $this->successResponse([
                'message' => 'Call status retrieved successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get call status', [
                'error' => $e->getMessage(),
                'channel' => $channel
            ]);

            return $this->errorResponse('Failed to get call status: ' . $e->getMessage());
        }
    }

    /**
     * 获取活动通道列表
     */
    public function getActiveChannels(Request $request): Response
    {
        try {
            $this->logger->info('Getting active channels');

            $result = $this->asteriskService->getActiveChannels();

            return $this->successResponse([
                'message' => 'Active channels retrieved successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get active channels', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to get active channels: ' . $e->getMessage());
        }
    }

    /**
     * 处理 SDP Offer
     */
    public function handleSdpOffer(Request $request): Response
    {
        $data = $request->getBodyParams();
        $sdpOffer = $data['sdp'] ?? '';
        $callId = $data['callId'] ?? '';

        if (empty($sdpOffer) || empty($callId)) {
            return $this->errorResponse('SDP offer and call ID are required');
        }

        try {
            $this->logger->info('Handling SDP offer', [
                'callId' => $callId
            ]);

            // 1. 处理 SDP offer 并生成 answer
            $result = $this->mediaManager->handleSipSdpOffer($sdpOffer, $callId);

            // 2. 获取 Asterisk 的 RTP 信息
            $asteriskRtpInfo = $this->asteriskService->getRtpInfoFromSdp($sdpOffer);

            // 3. 从 SDP answer 中获取 Janus 的 RTP 信息
            $janusRtpInfo = $this->mediaManager->getRtpInfoFromSdp($result['sdpAnswer']);

            // 4. 设置 RTP 转发
            $rtpResult = $this->mediaManager->setupAsteriskToJanusRtp(
                $callId,
                $asteriskRtpInfo,
                $janusRtpInfo
            );

            return $this->successResponse([
                'message' => 'SDP offer handled and RTP forwarding setup successfully',
                'data' => [
                    'sdpAnswer' => $result['sdpAnswer'],
                    'mediaInfo' => $result['mediaInfo'],
                    'rtp' => $rtpResult
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle SDP offer', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);

            return $this->errorResponse('Failed to handle SDP offer: ' . $e->getMessage());
        }
    }

    /**
     * 处理 SDP Answer
     */
    public function handleSdpAnswer(Request $request): Response
    {
        $data = $request->getBodyParams();
        $sdpAnswer = $data['sdp'] ?? '';
        $callId = $data['callId'] ?? '';

        if (empty($sdpAnswer) || empty($callId)) {
            return $this->errorResponse('SDP answer and call ID are required');
        }

        try {
            $this->logger->info('Handling SDP answer', [
                'callId' => $callId
            ]);

            // 1. 处理 SDP answer
            $result = $this->mediaManager->handleSipSdpAnswer($sdpAnswer, $callId);

            // 2. 更新 RTP 转发配置（如果需要）
            if (isset($result['mediaInfo']['rtp'])) {
                $this->mediaManager->updateRtpForwarding($callId, $result['mediaInfo']['rtp']);
            }

            return $this->successResponse([
                'message' => 'SDP answer handled successfully',
                'data' => [
                    'mediaInfo' => $result['mediaInfo']
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle SDP answer', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);

            return $this->errorResponse('Failed to handle SDP answer: ' . $e->getMessage());
        }
    }

    /**
     * 开始 RTP 转发
     */
    public function startRtpForwarding(Request $request): Response
    {
        $data = $request->getBodyParams();
        $callId = $data['callId'] ?? '';
        $targetIp = $data['targetIp'] ?? '';
        $targetPort = $data['targetPort'] ?? '';

        if (empty($callId) || empty($targetIp) || empty($targetPort)) {
            return $this->errorResponse('Call ID, target IP and port are required');
        }

        try {
            $this->logger->info('Starting RTP forwarding', [
                'callId' => $callId,
                'targetIp' => $targetIp,
                'targetPort' => $targetPort
            ]);

            // 开始 RTP 转发
            $result = $this->mediaManager->startRtpForwarding($callId, $targetIp, (int)$targetPort);

            return $this->successResponse([
                'message' => 'RTP forwarding started successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to start RTP forwarding', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);

            return $this->errorResponse('Failed to start RTP forwarding: ' . $e->getMessage());
        }
    }

    /**
     * 停止 RTP 转发
     */
    public function stopRtpForwarding(Request $request): Response
    {
        $data = $request->getBodyParams();
        $callId = $data['callId'] ?? '';

        if (empty($callId)) {
            return $this->errorResponse('Call ID is required');
        }

        try {
            $this->logger->info('Stopping RTP forwarding', [
                'callId' => $callId
            ]);

            // 停止 RTP 转发
            $result = $this->mediaManager->stopRtpForwarding($callId);

            return $this->successResponse([
                'message' => 'RTP forwarding stopped successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to stop RTP forwarding', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);

            return $this->errorResponse('Failed to stop RTP forwarding: ' . $e->getMessage());
        }
    }

    /**
     * 处理入站 SIP 呼叫
     */
    public function handleInboundCall(Request $request): Response
    {
        $data = $request->getBodyParams();
        $extension = $data['extension'] ?? '';
        $sipHeaders = $data['headers'] ?? [];

        if (empty($extension)) {
            return $this->errorResponse('Extension is required');
        }

        try {
            $this->logger->info('Handling inbound SIP call', [
                'extension' => $extension,
                'headers' => $sipHeaders
            ]);

            // 处理入站呼叫
            $callResult = $this->asteriskService->handleInboundCall($extension, $sipHeaders);

            // 获取 Asterisk 的 RTP 信息
            $asteriskRtpInfo = $this->asteriskService->getRtpInfo($callResult['channel']);

            // 获取 Janus 的 RTP 信息
            $janusRtpInfo = $this->mediaManager->getJanusRtpInfo((int)$callResult['roomId']);

            // 设置 RTP 转发
            $rtpResult = $this->mediaManager->setupAsteriskToJanusRtp(
                $callResult['callId'],
                $asteriskRtpInfo,
                $janusRtpInfo
            );

            return $this->successResponse([
                'message' => 'Inbound call handled successfully',
                'data' => [
                    'call' => $callResult,
                    'rtp' => $rtpResult
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle inbound call', [
                'error' => $e->getMessage(),
                'extension' => $extension
            ]);
            return $this->errorResponse('Failed to handle inbound call: ' . $e->getMessage());
        }
    }

    /**
     * 处理 SIP 响应
     */
    public function handleSipResponse(Request $request): Response
    {
        $data = $request->getBodyParams();
        $callId = $data['callId'] ?? '';
        $statusCode = (int)($data['statusCode'] ?? 0);
        $headers = $data['headers'] ?? [];

        if (empty($callId) || $statusCode === 0) {
            return $this->errorResponse('Call ID and status code are required');
        }

        try {
            $this->logger->info('Handling SIP response', [
                'callId' => $callId,
                'statusCode' => $statusCode
            ]);

            // 处理 SIP 响应
            $result = $this->asteriskService->handleSipResponse($callId, $statusCode, $headers);

            // 如果是成功响应，更新 RTP 转发
            if ($statusCode === 200) {
                $this->mediaManager->updateRtpForwarding($callId, $result['rtp'] ?? []);
            }

            return $this->successResponse([
                'message' => 'SIP response handled successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle SIP response', [
                'error' => $e->getMessage(),
                'callId' => $callId,
                'statusCode' => $statusCode
            ]);
            return $this->errorResponse('Failed to handle SIP response: ' . $e->getMessage());
        }
    }

    /**
     * 处理 SIP BYE 请求
     */
    public function handleSipBye(Request $request): Response
    {
        $data = $request->getBodyParams();
        $callId = $data['callId'] ?? '';
        $headers = $data['headers'] ?? [];

        if (empty($callId)) {
            return $this->errorResponse('Call ID is required');
        }

        try {
            $this->logger->info('Handling SIP BYE', [
                'callId' => $callId
            ]);

            // 处理 BYE 请求
            $result = $this->asteriskService->handleSipBye($callId, $headers);

            // 停止 RTP 转发
            $this->mediaManager->stopRtpForwarding($callId);

            return $this->successResponse([
                'message' => 'SIP BYE handled successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle SIP BYE', [
                'error' => $e->getMessage(),
                'callId' => $callId
            ]);
            return $this->errorResponse('Failed to handle SIP BYE: ' . $e->getMessage());
        }
    }
}
