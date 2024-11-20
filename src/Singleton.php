<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris;

/**
 * This trait ensures that a class can only have a single instance (singleton pattern). It provides a static method
 * `instance()` to access the instance, and prevents the object from being cloned, serialized, or unserialized.
 */
trait Singleton {
    /**
     * Holds the instance of the class using the trait.
     *
     * @var static|null The single instance of the class.
     */
    protected static $instance;

    /**
     * Returns the single instance of the class.
     *
     * If the instance does not already exist, it will be created using the provided options.
     *
     * @param mixed|null $options Optional parameter that can be passed to customize the instance creation.
     * @return static The singleton instance of the class.
     */
    public static function instance(mixed $options = null): static
    {
        if (!isset(static::$instance)) {
            static::$instance = new static($options);
        }

        return static::$instance;
    }

    /**
     * Restricts cloning of the singleton instance.
     * @throws \Exception
     */
    final public function __clone()
    {
        throw new \Exception("Cannot clone a singleton.");
    }

    /**
     * Restricts serializing the singleton instance.
     * @throws \Exception
     */
    final public function __sleep()
    {
        throw new \Exception("Cannot serialize a singleton.");
    }

    /**
     * Restricts unserializing the singleton instance.
     * @throws \Exception
     */
    final public function __wakeup()
    {
        throw new \Exception("Cannot unserialize a singleton.");
    }
}
