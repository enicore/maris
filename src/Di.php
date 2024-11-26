<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris;

use Psr\Container\ContainerInterface;

class Di
{
    private static ?ContainerInterface $container = null;

    public static function setContainer(ContainerInterface $container): void
    {
        self::$container = $container;
    }

    public static function auth()
    {
        return self::$container->get(Auth::class);
    }

    public static function db()
    {
        return self::$container->get(Database::class);
    }

    public static function request()
    {
        return self::$container->get(Request::class);
    }
}
