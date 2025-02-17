<?php

namespace App\Controllers;

use App\Services\AsteriskService;
use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;

class PbxController extends BaseController
{
    private AsteriskService $asteriskService;
    private Logger $logger;

    public function __construct(AsteriskService $asteriskService)
    {
        $this->asteriskService = $asteriskService;
        $this->logger = Logger::getInstance('pbx-controller');
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

            // 通过 Asterisk 发起呼叫到 Janus 房间
            $result = $this->asteriskService->initiateCall($extension, $roomId);

            $this->logger->info('Call initiated successfully', [
                'result' => $result
            ]);

            return $this->successResponse([
                'message' => 'Call initiated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to initiate call', [
                'error' => $e->getMessage(),
                'extension' => $extension,
                'roomId' => $roomId
            ]);

            return $this->errorResponse('Failed to initiate call: ' . $e->getMessage());
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
}
