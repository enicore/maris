<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Classes;

/**
 *
 * @package Enicore\Maris
 */
class Text
{
    /**
     * Converts an integer to a human-readable file size string.
     *
     * @param string|int $size The file size in bytes.
     * @return string The human-readable file size (e.g., "1.5 MB").
     */
    public static function sizeToString(string|int $size): string
    {
        if (!is_numeric($size)) {
            return "-";
        }

        $units = ["B", "kB", "MB", "GB", "TB", "PB"];
        $index = 0;

        while ($size >= 1000) {
            $size /= 1000;
            $index++;
        }

        return round($size, 1) . " " . $units[$index];
    }

    /**
     * Shortens a string to the specified length and appends "..." if necessary. If the string is shorter than the
     * specified length, it will be returned intact.
     *
     * @param string $string The string to shorten.
     * @param int $length The maximum length of the string.
     * @return string The shortened string with ellipsis if it exceeds the specified length.
     */

    public static function shortenString(string $string, int $length = 50): string
    {
        $string = trim($string);

        if (strlen($string) <= $length) {
            return $string;
        }

        $string = substr($string, 0, $length);

        if ($i = strrpos($string, " ")) {
            $string = substr($string, 0, $i);
        }

        return $string . "...";
    }

    /**
     * Removes the slash from the end of a string.
     *
     * @param string $string
     * @return string
     */
    public static function removeSlash(string $string): string
    {
        return rtrim($string, '/\\');
    }

    /**
     * Appends a trailing slash to the string if it is not already present.
     *
     * @param string $string The string to process.
     * @return string The string with a trailing slash.
     */
    public static function addSlash(string $string): string
    {
        return self::removeSlash($string) . '/';
    }

    /**
     * Converts a string representation of "true" or "false" into the corresponding boolean value.
     *
     * @param string $value The string to convert.
     * @return bool The boolean value, or false if $value is not 'true' or 'false'.
     */
    public static function strToBool(string $value): bool
    {
        return trim($value) === 'true';
    }
}
