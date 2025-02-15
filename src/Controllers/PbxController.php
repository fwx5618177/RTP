<?php

namespace App\Controllers;

use App\Services\AsteriskService;
use App\Http\Request;
use App\Http\Response;

class PbxController
{
    private AsteriskService $asteriskService;

    public function __construct(AsteriskService $asteriskService)
    {
        $this->asteriskService = $asteriskService;
    }

    public function makeCall(Request $request, Response $response): Response
    {
        $data = $request->parseRequestBody();
        $extension = $data['extension'] ?? '';
        $roomId = $data['roomId'] ?? '';

        if (empty($extension) || empty($roomId)) {
            return $response->body([
                'success' => false,
                'message' => 'Extension and room ID are required'
            ]);
        }

        try {
            // 通过 Asterisk 发起呼叫到 Janus 房间
            $result = $this->asteriskService->initiateCall($extension, $roomId);

            return $response->body([
                'success' => true,
                'message' => 'Call initiated successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return $response->body([
                'success' => false,
                'message' => 'Failed to initiate call: ' . $e->getMessage()
            ]);
        }
    }
}
