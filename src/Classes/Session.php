<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

use InvalidArgumentException;
use RuntimeException;

/**
 * Manages session data, providing convenient methods for storing, retrieving, checking, and removing session values.
 * Ensures that the session is active before each operation.
 *
 * @package Enicore\Maris
 */
class Session
{
    private static array $options = [
        'name'     => '',
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

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

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"],
                $params["httponly"]);
        }

        session_unset();
        session_destroy();
        session_write_close();
    }

    /**
     * Regenerates the session ID to prevent session fixation attacks.
     *
     * @return void
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * Sets the session cookie options used when the session starts. Unknown options throw an exception.
     *
     * - name: the cookie name (PHP's default, PHPSESSID, if empty)
     * - lifetime: the cookie lifetime in seconds (0: until the browser is closed)
     * - path, domain: the cookie scope
     * - secure: send the cookie over https only (browsers allow it on http://localhost too)
     * - httponly: hide the cookie from JavaScript
     * - samesite: Lax (default), Strict or None. Strict doesn't send the cookie when the site is opened from a link
     *   on another site or in an email, so logged-in users appear logged out on that first page.
     *
     * @param array $options The options to change, the others keep their values.
     * @return void
     */
    public static function setOptions(array $options): void
    {
        if ($unknown = array_diff_key($options, self::$options)) {
            throw new InvalidArgumentException("Unknown session options: " . implode(", ", array_keys($unknown)));
        }

        $sameSite = ucfirst(strtolower($options['samesite'] ?? 'Lax'));

        if (!in_array($sameSite, ["Lax", "Strict", "None"])) {
            throw new InvalidArgumentException("Invalid samesite session option: {$options['samesite']}");
        }

        self::$options = array_merge(self::$options, $options);
    }

    /**
     * Returns the session cookie options.
     *
     * @return array
     */
    public static function getOptions(): array
    {
        return self::$options;
    }

    /**
     * Starts the session if it hasn't been started yet.
     *
     * @param array $data An associative array of key-value pairs to initialize the session with.
     * @return void
     */
    public static function start(array $data = []): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $options = self::$options;

        if (!empty($options['name'])) {
            session_name($options['name']);
        }

        session_set_cookie_params([
            'lifetime' => (int)$options['lifetime'],
            'path'     => $options['path'],
            'domain'   => $options['domain'],
            'secure'   => (bool)$options['secure'],
            'httponly' => (bool)$options['httponly'],
            'samesite' => ucfirst(strtolower($options['samesite'])),
        ]);

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        // don't accept session ids the server didn't create (session fixation)
        ini_set('session.use_strict_mode', '1');

        if (!session_start()) {
            throw new RuntimeException("Failed to start the session.");
        }

        if (!empty($data)) {
            $_SESSION = $data;
        }
    }

    public static function getCsrfToken(): string
    {
        self::start();
        if (!self::has('csrf')) {
            self::set('csrf', bin2hex(random_bytes(32)));
        }
        return self::get('csrf');
    }
}
