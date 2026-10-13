<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

use RuntimeException;

/**
 * Handles application configuration, loaded from a PHP file that returns an array, from environment variables, or
 * both (the environment values override the file's).
 *
 * The environment values are described by a schema, with the keys in lowercase and the variables in uppercase:
 *
 * $config = Config::fromEnv([
 *     'db_host' => 'string',                                  // required
 *     'db_port' => ['type' => 'int', 'default' => 3306],      // optional
 *     'debug' => ['type' => 'bool', 'default' => false],
 *     'api_key' => ['type' => 'string', 'env' => 'MY_API_KEY'], // a different variable name
 * ], __DIR__ . '/../.env');
 *
 * The .env file fills in the variables the server's environment doesn't set, so the server's values take precedence.
 *
 * @package Enicore\Maris
 */
class Config
{
    private const TYPES = ['string', 'int', 'float', 'bool'];

    private array $config = [];
    private readonly string $file;

    /**
     * @param string|null $file Path to the PHP config file that returns an array. If empty, src/config.php next to the
     *                          directory of the running script; if null, no file.
     */
    public function __construct(?string $file = '')
    {
        if ($file === '') {
            $file = (string)realpath(dirname($_SERVER['SCRIPT_FILENAME']) . '/../src/config.php');
        }

        $this->file = (string)$file;

        if ($this->file !== '' && is_file($this->file)) {
            $data = require $this->file;
            if (is_array($data)) {
                $this->config = $data;
            } else {
                error_log("Config file must return an array: $this->file");
            }
        }
    }

    /**
     * Creates a config from environment variables only. See loadEnv().
     *
     * @param array $schema The values to load, see the class description.
     * @param string $envFile The .env file to load first, if it exists.
     * @return Config
     * @throws RuntimeException If a required value is missing or a value is invalid.
     */
    public static function fromEnv(array $schema, string $envFile = ''): Config
    {
        return (new Config(null))->loadEnv($schema, $envFile);
    }

    /**
     * Loads the values described by the schema from the environment variables, converted to their types. Optional
     * values (with a default) that aren't set get their default.
     *
     * @param array $schema The values to load: key => type, or key => ['type' => ..., 'default' => ..., 'env' => ...].
     * @param string $envFile The .env file to load first, if it exists.
     * @return $this
     * @throws RuntimeException If a required value is missing or a value is invalid; the message lists all of them.
     */
    public function loadEnv(array $schema, string $envFile = ''): static
    {
        if ($envFile !== '') {
            self::loadEnvFile($envFile);
        }

        $errors = [];

        foreach ($schema as $key => $info) {
            $info = is_array($info) ? $info : ['type' => $info];
            $type = $info['type'] ?? 'string';
            $name = $info['env'] ?? strtoupper($key);

            if (!in_array($type, self::TYPES, true)) {
                throw new RuntimeException("Invalid config type for $key: $type");
            }

            $value = getenv($name);

            if ($value === false || trim($value) === '') {
                if (array_key_exists('default', $info)) {
                    $this->config[$key] = $info['default'];
                } else {
                    $errors[] = "missing $name";
                }
                continue;
            }

            $value = self::convert(trim($value), $type);

            if ($value === null) {
                $errors[] = "invalid $name (expected $type)";
                continue;
            }

            $this->config[$key] = $value;
        }

        if ($errors) {
            throw new RuntimeException("Invalid configuration: " . implode(", ", $errors));
        }

        return $this;
    }

    /**
     * Puts the variables of a .env file into the environment, where they aren't set yet. Lines are NAME=value, with
     * optional "export " in front; values can be quoted; lines starting with # are comments, as are " #" and what
     * follows in unquoted values.
     *
     * @param string $file
     * @return bool False if the file doesn't exist or can't be read.
     */
    public static function loadEnvFile(string $file): bool
    {
        if (!is_file($file) || ($lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) === false) {
            return false;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim(preg_replace('/^export\s+/', '', $name));
            $value = trim($value);

            if ($name === '' || getenv($name) !== false) {
                continue;
            }

            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[1] === '"' ? stripcslashes($matches[2]) : $matches[2];
            } else {
                $value = trim(preg_replace('/\s+#.*$/', '', $value));
            }

            putenv("$name=$value");
        }

        return true;
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

    /**
     * Returns all the configuration values.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Converts an environment value to the type, or returns null if it isn't valid for the type.
     */
    private static function convert(string $value, string $type): string|int|float|bool|null
    {
        return match ($type) {
            'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'float' => is_numeric($value) ? (float)$value : null,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            default => $value,
        };
    }
}
