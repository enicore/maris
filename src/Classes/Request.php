<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

use Exception;

/**
 * Handles request data from GET, POST, and JSON input, providing access to and manipulation of request parameters.
 * Integrates a singleton pattern and dependency injection.
 *
 * @package Enicore\Maris
 */
class Request
{
    private array $data = [];

    public function __construct()
    {
        // Initialize $data with values from GET, POST, and JSON input (often used for AJAX requests).
        try {
            $this->data = array_merge(
                $_GET ?? [],
                $_POST ?? [],
                json_decode(file_get_contents('php://input'), true) ?? []
            );
        } catch (Exception) {
        }
    }

    /**
     * Returns a value from the request if it exists, or a default value if it doesn't.
     *
     * @param string $key The key to retrieve from the request data.
     * @param mixed|null $default The default value to return if the key does not exist.
     * @return mixed The value associated with the key or the default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Returns the decoded value of the 'id' from the request.
     *
     * @return int|bool The decoded ID as an integer, or false if the 'id' key is absent or invalid.
     */
    public function getDecodedId(): int|bool
    {
        return empty($this->data['id']) ? false : Code::decodeId($this->data['id']);
    }

    /**
     * Checks if a key exists in the request data.
     *
     * @param string $key The key to check in the request data.
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Returns all request data as an array.
     *
     * @return array The entire request data array.
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Stores a value in the request data.
     *
     * @param string $key The key to set in the request data.
     * @param mixed $value The value to store.
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Replaces the entire request data array with a new set of data.
     *
     * @param array $data The new data to set.
     * @return void
     */
    public function setAll(array $data): void
    {
        $this->data = $data;
    }
}
