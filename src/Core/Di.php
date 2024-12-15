<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Core;

use BadMethodCallException;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * The Di class offers a centralized solution for accessing services via dependency injection (DI) in scenarios where
 * constructor injection is not practical. It supports static retrieval of predefined services from a PSR-11 compliant
 * container. By registering a container and defining services, the Di class streamlines service management and
 * facilitates effortless integration with DI frameworks.
 *
 * Example of usage with DI\ContainerBuilder:
 *
 * $containerBuilder = new DI\ContainerBuilder();
 * $containerBuilder->addDefinitions([
 *     Database::class => DI\factory(function () {
 *         return new Database(
 *             MYSQL_HOST,
 *             MYSQL_PORT,
 *             MYSQL_DATABASE,
 *             MYSQL_USERNAME,
 *             MYSQL_PASSWORD
 *         );
 *     }),
 * ]);
 *
 * $container = $containerBuilder->build();
 * Enicore\Maris\Di::setContainer($container);
 *
 * @method static Auth auth()
 * @method static Database db()
 * @method static Request request()
 *
 * @package Enicore\Maris
 */
class Di
{
    private static ?ContainerInterface $container = null;

    private static array $services = [
        'auth'    => Auth::class,
        'db'      => Database::class,
        'request' => Request::class,
    ];

    /**
     * Set the DI container. Can only be set once.
     *
     * @throws RuntimeException if the container is already set.
     */
    public static function setContainer(ContainerInterface $container): void
    {
        if (self::$container !== null) {
            throw new RuntimeException('[Di] Container is already set.');
        }

        self::$container = $container;
    }

    /**
     * Retrieve a service by name.
     *
     * @throws RuntimeException if the container is not initialized.
     * @throws BadMethodCallException if the requested service is not registered.
     * @throws RuntimeException if the service cannot be retrieved from the container.
     */
    public static function get(string $name): mixed
    {
        if (self::$container === null) {
            throw new RuntimeException("[Di] Container not initialized. Cannot retrieve service '$name'.");
        }

        if (!self::has($name)) {
            throw new BadMethodCallException("[Di] Service '$name' does not exist.");
        }

        try {
            return self::$container->get(self::$services[$name]);
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            throw new RuntimeException(
                "[Di] Cannot retrieve service '$name': " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Register a new service.
     *
     * @throws InvalidArgumentException if the provided class does not exist.
     */
    public static function add(string $name, string $class): void
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("[Di] Class '$class' does not exist.");
        }

        self::$services[$name] = $class;
    }

    /**
     * Check if a service is registered.
     */
    public static function has(string $name): bool
    {
        return isset(self::$services[$name]);
    }

    /**
     * Dynamically handle static method calls to retrieve services.
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return self::get($name);
    }
}
