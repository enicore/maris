<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris;

/**
 * Manages session data using a singleton pattern, providing convenient methods for storing, retrieving, checking, and
 * removing session values. Ensures that the session is active before each operation.
 *
 * @package Enicore\Maris
 */
class Session
{
    /**
     * Returns a value from the session if it exists, or a default value if it doesn't.
     *
     * @param string $key The session key to retrieve.
     * @param mixed|null $default The default value to return if the key does not exist.
     * @return mixed The value associated with the session key or the default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Checks if a session key exists.
     *
     * @param string $key The session key to check.
     * @return bool True if the session key exists, false otherwise.
     */
    public static function has(string $key): bool
    {
        self::start();
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Stores a value in the session.
     *
     * @param string $key The session key to set.
     * @param mixed $value The value to store in the session.
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Removes a session key if it exists.
     *
     * @param string $key The session key to remove.
     * @return void
     */
    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Destroys the current session and clears all session data.
     *
     * @return void
     */
    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    /**
     * Starts the session if it hasn't been started yet.
     *
     * @return void
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}