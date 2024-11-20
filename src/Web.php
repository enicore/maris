<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris;

class Web
{
    /**
     * Returns the full URL of the current host (including protocol).
     *
     * @return string The host URL (e.g., "https://example.com").
     */

    public static function getHost(): string
    {
        return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" .
            $_SERVER['SERVER_NAME'];
    }

    /**
     * Returns the current URL, optionally appending a specified path. If $hostOnly is true, only the host is returned.
     * If $cut is provided, it will trim the resulting URL at the specified parts.
     *
     * @param string $path The path to append to the URL (optional).
     * @param bool $hostOnly Whether to return only the host.
     * @param bool $cut The string or array of strings to cut from the URL (optional).
     * @return string|bool The full URL or the host; false if the host cannot be determined.
     */
    public static function getUrl(string $path = "", bool $hostOnly = false, bool $cut = false): string|bool
    {
        // if absolute path specified, simply return it
        if (strpos($path, "://")) {
            return $path;
        }

        $requestUri = empty($_SERVER['REQUEST_URI']) ? "_" : $_SERVER['REQUEST_URI'];
        $parts = parse_url($requestUri);
        $urlPath = $parts['path'] ?? "";

        if (!empty($parts['scheme'])) {
            $scheme = strtolower($parts['scheme']) == "https" ? "https" : "http";
        } else {
            $scheme = empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != "on" ? "http" : "https";
        }

        if (!empty($parts['host'])) {
            $host = $parts['host'];
        } else {
            $host = empty($_SERVER['HTTP_HOST']) ? false : $_SERVER['HTTP_HOST'];

            if (empty($host)) {
                $host = empty($_SERVER['SERVER_NAME']) ? false : $_SERVER['SERVER_NAME'];
            }
        }

        if (!empty($parts['port'])) {
            $port = $parts['port'];
        } else {
            $port = empty($_SERVER['SERVER_PORT']) ? "80" : $_SERVER['SERVER_PORT'];
        }

        // if url not specified in the config, check for proxy values
        empty($_SERVER['HTTP_X_FORWARDED_PROTO']) || ($scheme = $_SERVER['HTTP_X_FORWARDED_PROTO']);
        empty($_SERVER['HTTP_X_FORWARDED_HOST']) || ($host = $_SERVER['HTTP_X_FORWARDED_HOST']);
        empty($_SERVER['HTTP_X_FORWARDED_PORT']) || ($port = $_SERVER['HTTP_X_FORWARDED_PORT']);

        // if full url specified but without the protocol, prepend http or https and return.
        // we can't just leave it as is because roundcube will prepend the current domain
        if (str_starts_with($path, "//")) {
            return $scheme . ":" . $path;
        }

        // we have to have the host
        if (empty($host)) {
            return false;
        }

        // if need host only, return it
        if ($hostOnly) {
            return $host;
        }

        // format port
        if ($port && is_numeric($port) && $port != "443" && $port != "80") {
            $port = ":" . $port;
        } else {
            $port = "";
        }

        // in cpanel $urlPath will have index.php at the end
        if (str_ends_with($urlPath, ".php")) {
            $urlPath = dirname($urlPath);
        }

        // if path begins with a slash, cut it
        if (str_starts_with($path, "/")) {
            $path = substr($path, 1);
        }

        $result = Text::addSlash($scheme . "://" . $host . $port . $urlPath);

        // if paths to cut were specified, find and cut the resulting url
        if ($cut) {
            if (!is_array($cut)) {
                $cut = [$cut];
            }

            foreach ($cut as $val) {
                if (($pos = strpos($result, $val)) !== false) {
                    $result = substr($result, 0, $pos);
                }
            }
        }

        return $result . $path;
    }

    /**
     * Creates a permalink from the given text suitable for use in a URL.
     *
     * @param string $text The text to convert to a permalink.
     * @return bool|string Returns the permalink as a string, or false if the input text is empty or invalid.
     */
    public static function createPermalink(string $text): bool|string
    {
        if (!($text = trim($text))) {
            return false;
        }

        $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
        $text = preg_replace("%[^-/+|\w ]%", "-", $text);
        $text = strtolower(trim($text, "-"));
        return (string)preg_replace("/[\/_|+ -]+/", "-", $text);
    }

    /**
     * Returns the current browser's language from the HTTP_ACCEPT_LANGUAGE header.
     *
     * @return string The detected language code (e.g., "en", "de").
     */
    public static function getBrowserLanguage(): string
    {
        $result = "en";

        if (empty($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
            return $result;
        }

        $lan = [];

        foreach (explode(",", $_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $val) {
            // check for q-value and create associative array. No q-value means 1
            if (preg_match("/(.*);q=([0-1]{0,1}.\d{0,4})/i", $val, $matches)) {
                $lan[$matches[1]] = (float)$matches[2];
            } else {
                $lan[$val] = 1.0;
            }
        }

        // return default language (highest q-value)
        $value = 0.0;

        foreach ($lan as $key => $val) {
            if ($val > $value) {
                $value = (float)$val;
                $result = $key;
            }
        }

        return strtolower($result);
    }

    /**
     * Validates an email address, optionally checking DNS records for the domain. This function uses PHP's `filter_var`
     * to check if the email format is valid. If the `$checkDns` flag is set to true, it also checks if the domain part
     * of the email has valid DNS records using the `checkdnsrr` function.
     *
     * @param string $email The email address to validate.
     * @param bool $checkDns Flag to determine if DNS records for the domain should be checked (default is true).
     * @return bool Returns true if the email is valid, false otherwise.
     */
    public static function validateEmail(string $email, bool $checkDns = true): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if ($checkDns) {
            $array = explode("@", $email);
            $domain = end($array);

            // check the mx record (dot at the end, so it doesn't get treated as a subdomain of the local domain)
            if (!checkdnsrr($domain . ".")) {
                return false;
            }
        }

        return true;
    }

}