<?php
/**
 * Enicore Maris.
 * Copyright 2025 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Validated access to request values: the posted values (fromBody) or the query string (fromQuery). In Slim, add
 * $app->addBodyParsingMiddleware() so JSON bodies are parsed too.
 *
 * The typed getters throw an InputException with a message that can be shown to the user when the value is missing
 * or invalid, unless a default is given for missing values:
 *
 * try {
 *     $input = Input::fromBody($request);
 *     $name = $input->getString('name', 100);
 *     $count = $input->getInt('count', default: 1);
 * } catch (InputException $e) {
 *     return $this->sendError($response, $e->getMessage(), 400);
 * }
 *
 * @package Enicore\Maris
 */
class Input
{
    public function __construct(private array $data = [])
    {
    }

    /**
     * Creates the input from the request's parsed body (posted form or JSON values).
     *
     * @param Request $request
     * @return Input
     */
    public static function fromBody(Request $request): Input
    {
        $data = $request->getParsedBody();
        return new Input(is_array($data) ? $data : (is_object($data) ? get_object_vars($data) : []));
    }

    /**
     * Creates the input from the request's query string.
     *
     * @param Request $request
     * @return Input
     */
    public static function fromQuery(Request $request): Input
    {
        return new Input($request->getQueryParams());
    }

    /**
     * Returns the raw value, or the default if it doesn't exist.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Returns true if the value exists and isn't null.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Returns all the values.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Returns the trimmed string, shortened to $maxLength characters if given.
     *
     * @param string $key
     * @param int|null $maxLength
     * @param string|null $default Returned if the value is missing; without it, a missing value throws.
     * @param bool $allowEmpty If false, an empty string is treated as missing.
     * @return string
     * @throws InputException
     */
    public function getString(string $key, ?int $maxLength = null, ?string $default = null,
                              bool $allowEmpty = true): string
    {
        $value = $this->data[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            $value = (string)$value;
        }

        if ($value !== null && !is_string($value)) {
            throw new InputException("Invalid value: $key", $key);
        }

        $value = $value === null ? null : trim($value);

        if ($value === null || (!$allowEmpty && $value === '')) {
            return $this->missing($key, $default);
        }

        return $maxLength !== null && $maxLength > 0 ? mb_substr($value, 0, $maxLength) : $value;
    }

    /**
     * Returns the integer value; strings must be whole numbers.
     *
     * @param string $key
     * @param int|null $default Returned if the value is missing or empty; without it, a missing value throws.
     * @return int
     * @throws InputException
     */
    public function getInt(string $key, ?int $default = null): int
    {
        $value = $this->scalar($key);

        if ($value === null) {
            return $this->missing($key, $default);
        }

        if (($result = filter_var($value, FILTER_VALIDATE_INT)) === false) {
            throw new InputException("Invalid number: $key", $key);
        }

        return $result;
    }

    /**
     * Returns the float value.
     *
     * @param string $key
     * @param float|null $default Returned if the value is missing or empty; without it, a missing value throws.
     * @return float
     * @throws InputException
     */
    public function getFloat(string $key, ?float $default = null): float
    {
        $value = $this->scalar($key);

        if ($value === null) {
            return $this->missing($key, $default);
        }

        if (!is_numeric($value)) {
            throw new InputException("Invalid number: $key", $key);
        }

        return (float)$value;
    }

    /**
     * Returns the boolean value: true, 1, "1", "true", "on", "yes" are true; false, 0, "0", "false", "off", "no" are
     * false. Checkboxes send no value when unchecked, so give them a false default.
     *
     * @param string $key
     * @param bool|null $default Returned if the value is missing or empty; without it, a missing value throws.
     * @return bool
     * @throws InputException
     */
    public function getBool(string $key, ?bool $default = null): bool
    {
        $value = $this->scalar($key);

        if ($value === null) {
            return $this->missing($key, $default);
        }

        if (($result = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)) === null) {
            throw new InputException("Invalid value: $key", $key);
        }

        return $result;
    }

    /**
     * Returns the trimmed, valid email address.
     *
     * @param string $key
     * @param string|null $default Returned if the value is missing or empty; without it, a missing value throws.
     * @return string
     * @throws InputException
     */
    public function getEmail(string $key, ?string $default = null): string
    {
        $value = $this->getString($key, null, '', false);

        if ($value === '') {
            return $this->missing($key, $default);
        }

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InputException("Invalid email address: $key", $key);
        }

        return $value;
    }

    /**
     * Returns the array value.
     *
     * @param string $key
     * @param array|null $default Returned if the value is missing; without it, a missing value throws.
     * @return array
     * @throws InputException
     */
    public function getArray(string $key, ?array $default = null): array
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return $this->missing($key, $default);
        }

        if (!is_array($value)) {
            throw new InputException("Invalid value: $key", $key);
        }

        return $value;
    }

    /**
     * Returns a scalar value, or null if it's missing or an empty string (empty form fields).
     *
     * @throws InputException
     */
    private function scalar(string $key): string|int|float|bool|null
    {
        $value = $this->data[$key] ?? null;

        if ($value !== null && !is_scalar($value)) {
            throw new InputException("Invalid value: $key", $key);
        }

        return is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value);
    }

    /**
     * Returns the default for a missing value, or throws if there's none.
     *
     * @throws InputException
     */
    private function missing(string $key, mixed $default): mixed
    {
        if ($default === null) {
            throw new InputException("Missing value: $key", $key);
        }

        return $default;
    }
}
