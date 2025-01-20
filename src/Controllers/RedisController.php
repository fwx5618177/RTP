<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\RedisService;
use Psr\Container\ContainerInterface;

class RedisController extends BaseController
{
    private RedisService $redisService;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->redisService = new RedisService();
    }

    public function set(Request $request): Response
    {
        $data = $request->getBodyParams();

        if (empty($data['key']) || !isset($data['value'])) {
            return $this->errorResponse('Key and value are required');
        }

        $ttl = $data['ttl'] ?? null;
        $result = $this->redisService->set($data['key'], $data['value'], $ttl);

        if (!$result) {
            return $this->errorResponse('Failed to set Redis key');
        }

        return $this->successResponse([
            'message' => 'Successfully set Redis key',
            'key' => $data['key']
        ]);
    }

    public function get(Request $request, string $key): Response
    {
        $value = $this->redisService->get($key);

        if ($value === null) {
            return $this->errorResponse('Key not found', 404);
        }

        return $this->successResponse([
            'key' => $key,
            'value' => $value
        ]);
    }

    public function exists(Request $request, string $key): Response
    {
        $exists = $this->redisService->exists($key);

        return $this->successResponse([
            'key' => $key,
            'exists' => $exists
        ]);
    }

    public function delete(Request $request, string $key): Response
    {
        $result = $this->redisService->delete($key);

        if (!$result) {
            return $this->errorResponse('Failed to delete key or key not found');
        }

        return $this->successResponse([
            'message' => 'Successfully deleted key',
            'key' => $key
        ]);
    }
}
