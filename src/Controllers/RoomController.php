<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTO\RoomDTO;
use App\Exceptions\RoomException;
use App\Http\Request;
use App\Http\Response;
use App\Logs\Logger;
use App\Services\RoomService;
use Psr\Container\ContainerInterface;

class RoomController extends BaseController
{
    private RoomService $roomService;
    private Logger $logger;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->roomService = $container->get(RoomService::class);
        $this->logger = Logger::getInstance('room-controller');
    }

    public function createRoom(Request $request): Response
    {
        $data = $request->getBodyParams();
        $this->logger->info('Creating room with data', ['data' => $data]);

        // 添加参数验证
        if (empty($data['userId'])) {
            $this->logger->warning('userId is required but was empty');

            return (new Response())
                ->setStatusCode(400)
                ->setBody(['error' => 'userId is required']);
        }

        if (empty($data['roomName'])) {
            $this->logger->warning('roomName is required but was empty');

            return (new Response())
                ->setStatusCode(400)
                ->setBody(['error' => 'roomName is required']);
        }

        $roomDTO = new RoomDTO($data['userId'], $data['roomName'], $data['config'] ?? []);
        $this->logger->debug('Created RoomDTO', ['dto' => [
            'userId' => $roomDTO->getUserId(),
            'roomName' => $roomDTO->getRoomName(),
            'config' => $roomDTO->getConfig(),
        ]]);

        try {
            $room = $this->roomService->createRoom($roomDTO);
            $this->logger->info('Room created successfully', [
                'roomId' => $room->getRoomId(),
                'createdAt' => $room->getCreatedAt()->format('c'),
            ]);

            return (new Response())
                ->setStatusCode(201)
                ->setBody([
                    'roomId' => $room->getRoomId(),
                    'createdAt' => $room->getCreatedAt()->format('c'),
                    'config' => $room->getConfig(),
                    'userId' => $roomDTO->getUserId(),
                    'roomName' => $roomDTO->getRoomName(),
                ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create room', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return (new Response())
                ->setStatusCode(400)
                ->setBody(['error' => $e->getMessage()]);
        }
    }

    public function joinRoom(Request $request): Response
    {
        try {
            $data = $request->getBodyParams();

            // Validate required parameters
            if (empty($data['roomId'])) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'roomId is required']);
            }

            if (empty($data['userId'])) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'userId is required']);
            }

            $roomId = $data['roomId'];
            $userId = $data['userId'];

            // Check for SIP headers
            if ($request->getHeader('X-Conference-Room') && $request->getHeader('X-Conference-Server')) {
                // Handle SIP call routing
                return $this->handleSipCall($request, $roomId, $userId);
            }

            $result = $this->roomService->joinRoom($roomId, $userId);

            return (new Response())
                ->setStatusCode(200)
                ->setBody($result);
        } catch (\Exception $e) {
            $this->logger->error('Failed to join room', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $statusCode = 500;
            if ($e instanceof RoomException) {
                $statusCode = 404;
            }

            return (new Response())
                ->setStatusCode($statusCode)
                ->setBody(['error' => $e->getMessage()]);
        }
    }

    public function leaveRoom(Request $request): Response
    {
        try {
            $data = $request->getBodyParams();

            // Validate required parameters
            if (empty($data['roomId'])) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'roomId is required']);
            }

            if (empty($data['userId'])) {
                return (new Response())
                    ->setStatusCode(400)
                    ->setBody(['error' => 'userId is required']);
            }

            $roomId = $data['roomId'];
            $userId = $data['userId'];

            $this->roomService->leaveRoom($roomId, $userId);

            return (new Response())->setStatusCode(204);
        } catch (\Exception $e) {
            $this->logger->error('Failed to leave room', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $statusCode = 500;
            if ($e instanceof RoomException) {
                $statusCode = 404;
            }

            return (new Response())
                ->setStatusCode($statusCode)
                ->setBody(['error' => $e->getMessage()]);
        }
    }

    private function handleSipCall(Request $request, string $roomId, string $userId): Response
    {
        // TODO: Implement SIP call routing logic
        // This would involve calling the internal API to bridge the call to Janus server
        // and updating Redis with the participant information

        return (new Response())
            ->setStatusCode(200)
            ->setBody([
                'message' => 'SIP call routed successfully',
                'roomId' => $roomId,
                'userId' => $userId,
            ]);
    }
}
