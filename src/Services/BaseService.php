<?php

namespace App\Services;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

abstract class BaseService
{
    protected EntityManagerInterface $entityManager;
    protected ContainerInterface $container;

    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->entityManager = $this->container->get(EntityManagerInterface::class);
    }
}
