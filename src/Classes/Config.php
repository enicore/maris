<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

/**
 * Handles application configuration by loading settings from a PHP file that returns an array.
 *
 * @package Enicore\Maris
 */
class Config
{
    private array $config;

    /**
     * @param string $file Path to the PHP config file that returns an array.
     */
    public function __construct(private readonly string $file = '')
    {
        if (empty($file)) {
            $file = realpath(dirname($_SERVER['SCRIPT_FILENAME']) . '/../src/config.php');
        }

        if (is_file($this->file)) {
            $data = require $this->file;
            if (is_array($data)) {
                $this->config = $data;
            } else {
                error_log("Config file must return an array: $this->file");
                $this->config = [];
            }
        } else {
            $this->config = [];
        }
    }

    /**
     * Retrieves a configuration value by key.
     *
     * @param string $key The configuration key.
     * @param mixed $default Default value to return if key does not exist.
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }

    /**
     * Checks if a configuration key exists.
     *
     * @param string $key The configuration key.
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config);
    }

    /**
     * Sets a configuration value by key.
     *
     * @param string $key The configuration key.
     * @param mixed $value The value to set.
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }
}
