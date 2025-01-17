<?php

namespace App\Utils;

use Psr\Container\ContainerInterface;

class Container
{
    private static ?ContainerInterface $instance = null;

    public static function setInstance(ContainerInterface $container): void
    {
        self::$instance = $container;
    }

    public static function getInstance(): ContainerInterface
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Container has not been initialized');
        }
        return self::$instance;
    }
}
