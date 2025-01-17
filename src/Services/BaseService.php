<?php

namespace App\Services;

use App\Utils\Container;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;

abstract class BaseService
{
    protected EntityManager $entityManager;
    protected ContainerInterface $container;

    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->entityManager = $this->container->get(EntityManager::class);
    }
}
