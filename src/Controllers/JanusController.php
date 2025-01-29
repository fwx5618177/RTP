<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Gateway\JanusGateway;

class JanusController
{
    private JanusGateway $janusGateway;

    public function __construct()
    {
        $this->janusGateway = new JanusGateway();
    }

    public function handleMessage(Request $request, string $sessionId, string $handleId): Response
    {
        try {
            $body = $request->getBodyParams();
            $response = $this->janusGateway->sendRequest("$sessionId/$handleId", $body);

            return new Response([
                'success' => true,
                'data' => $response,
                'code' => 200
            ]);
        } catch (\Exception $e) {
            return new Response([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    public function handleTrickle(Request $request, string $sessionId, string $handleId): Response
    {
        try {
            $body = $request->getBodyParams();
            $response = $this->janusGateway->sendRequest("$sessionId/$handleId/trickle", [
                'candidate' => $body['candidate']
            ]);

            return new Response([
                'success' => true,
                'data' => $response,
                'code' => 200
            ]);
        } catch (\Exception $e) {
            return new Response([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }
}
