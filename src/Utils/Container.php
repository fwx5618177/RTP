<?php

namespace App\Utils;

use DI\ContainerBuilder;
use App\Services\UserService;
use Doctrine\ORM\EntityManager;
use App\Providers\DatabaseServiceProvider;

class Container
{
    private static $instance = null;

    public static function setInstance($container): void
    {
        self::$instance = $container;
    }
    public static function getInstance()
    {
        if (self::$instance === null) {
            $builder = new ContainerBuilder();
            $builder->addDefinitions([
                EntityManager::class => function () {
                    return DatabaseServiceProvider::getEntityManager();
                },
                UserService::class => function ($container) {
                    return new UserService($container->get(EntityManager::class));
                },
            ]);

            self::$instance = $builder->build();
        }

        return self::$instance;
    }
}
