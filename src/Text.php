<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris;

class Text
{
    /**
     * Converts an integer to a human-readable file size string.
     *
     * @param string|int $size The file size in bytes.
     * @return string The human-readable file size (e.g., "1.5 MB").
     */

    static public function sizeToString(string|int $size): string
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
     * @return bool|string The boolean value or the original string if not "true" or "false".
     */
    public static function strToBool(string $value): bool|string
    {
        return match ($value) {
            "true" => true,
            "false" => false,
            default => $value,
        };
    }

    /**
     * Finds the last occurrence of a substring (`needle`) in a string (`haystack`), starting from a given position (`offset`).
     *
     * @param string $haystack The string in which to search.
     * @param string $needle The substring to search for.
     * @param int $offset The position from which to start the search (default is 0).
     * @return bool|int Returns the position of the last occurrence of the substring, or false if not found.
     */
    public static function backwardStrpos(string $haystack, string $needle, int $offset = 0): bool|int
    {
        if (empty($needle)) {
            return false;
        }

        $length = strlen($haystack);
        $offset = $offset > 0 ? $length - $offset : abs($offset);
        $pos = strpos(strrev($haystack), strrev($needle), $offset);
        return ($pos === false) ? false : $length - $pos - strlen($needle);
    }
}
