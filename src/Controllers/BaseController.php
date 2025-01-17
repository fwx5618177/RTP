<?php

namespace App\Controllers;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

abstract class BaseController
{
    protected EntityManagerInterface $entityManager;
    protected ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }
}

    protected function successResponse($data, int $statusCode = 200): Response
    {
        return new Response(
            $statusCode,
            ['Content-Type' => 'application/json'],
            ['success' => true, 'data' => $data]
        );
    }

    protected function errorResponse(string $message, int $statusCode = 400): Response
    {
        return new Response(
            $statusCode,
            ['Content-Type' => 'application/json'],
            ['success' => false, 'error' => $message]
        );
    }
}
