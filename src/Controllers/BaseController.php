<?php

namespace App\Controllers;

use App\Http\Response;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;

abstract class BaseController
{
    protected EntityManager $entityManager;
    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->entityManager = $container->get(EntityManager::class);
    }

    protected function successResponse($data, int $statusCode = 200): Response
    {
        return (new Response(['success' => true, 'data' => $data, 'code' => 200, 'time' => time()], $statusCode))
            ->header('Content-Type', 'application/json');
    }

    protected function errorResponse(string $message, int $statusCode = 400): Response
    {
        return (new Response(['success' => false, 'error' => $message, 'code' => 9999, 'time' => time()], $statusCode))
            ->header('Content-Type', 'application/json');
    }
}
