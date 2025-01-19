<?php

namespace App\Utils;

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
            throw new \Exception('Container is not initialized');
        }

        return self::$instance;
    }
}
