<?php
/**
 * Enicore Maris.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

use InvalidArgumentException;

/**
 * Thrown by Input when a required value is missing or invalid. The message can be shown to the user.
 *
 * @package Enicore\Maris
 */
class InputException extends InvalidArgumentException
{
    public function __construct(string $message, private readonly string $key = '')
    {
        parent::__construct($message);
    }

    /**
     * Returns the key of the missing or invalid value.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }
}
