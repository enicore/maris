<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Utils;

use Exception;

/**
 * Provides utility functions for encoding, decoding, and encrypting data. Includes methods for obfuscating IDs,
 * handling encryption/decryption, and encoding integers into short string representations. The class supports multiple
 * character sets for base encoding, such as BASE_36, BASE_62, and BASE_92, and implements methods for compressing and
 * encrypting data.
 *
 * @package Enicore\Maris
 */
class Code
{
    protected const string IDENTIFIER = "A8";
    protected const array DATA_TYPES = ["boolean", "integer", "double", "string", "array", "object", "NULL"];
    public const string BASE_36_CHARSET = "0123456789abcdefghijklmnopqrstuvwxyz";
    public const string BASE_62_CHARSET = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
    public const string BASE_92_CHARSET = "!\"#$%&'()*+-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`".
        "abcdefghijklmnopqrstuvwxyz{}~"; // excludes characters that can be used as separators: | and ,

    /**
     * Encodes an ID to an obfuscated 12-character string. The output size will increase for IDs larger than 999999999.
     * Returns null if the ID <= 0.
     *
     * @param string|int $id The ID to encode.
     * @param bool $randomized Whether to randomize the encoding.
     * @return string|null The encoded ID or null if invalid.
     */
    public static function encodeId(string|int $id, bool $randomized = false): ?string
    {
        $id = (int)$id;

        if ($id <= 0) {
            return null;
        }

        $hash = hash("sha256", $id);
        $salt = $randomized ? dechex(rand(16, 255)) : substr($hash, -2);
        $alphabet = self::getShuffledAlphabet($salt);
        $id = dechex($id);
        $len = strlen($id);
        $id = ($len < 8 ? substr($hash, 0, 8 - $len) : "") . $id . dechex($len + 16);

        foreach (str_split($id) as $key => $ch) {
            $id[$key] = $alphabet[$ch];
        }

        return $id . $salt;
    }

    /**
     * Decodes an obfuscated ID encoded by encodeId. Returns false if the string cannot be decoded.
     *
     * @param string $string The encoded string to decode.
     * @return int|null The decoded ID or null if invalid.
     */
    public static function decodeId(string $string): ?int
    {
        if (empty($string)) {
            return null;
        }

        $salt = substr($string, -2);
        $string = substr($string, 0, -2);
        $alphabet = self::getShuffledAlphabet($salt, true);

        foreach (str_split($string) as $key => $ch) {
            if (isset($alphabet[$ch])) {
                $string[$key] = $alphabet[$ch];
            }
        }

        $len = hexdec(substr($string, -2)) - 16;

        if ($len < 0) {
            return null;
        }

        $result = hexdec(substr($string, ($len + 2) * -1, $len));

        return is_float($result) ? null : $result;
    }

    /**
     * Encrypts a variable, detecting its type and storing the encrypted string with the variable type.
     *
     * @param mixed $data The data to encrypt.
     * @param string $key The encryption key.
     * @param bool $hexEncode Whether to return the result as hex-encoded string.
     * @return string|bool The encrypted string or false on failure.
     */
    public static function encrypt(mixed $data, string $key, bool $hexEncode = true): string|bool
    {
        $type = gettype($data);

        switch ($type) {
            case "boolean":
                $data = $data ? "1" : "0";
                $compress = false;
                break;
            case "integer":
            case "double":
                $data = (string)$data;
                $compress = false;
                break;
            case "array":
            case "object":
                $data = json_encode($data);
                $compress = true;
                break;
            case "NULL":
                $data = "0";
                $compress = false;
                break;
            case "string":
                $data = (string)$data;
                if (empty($data)) {
                    return "";
                }
                $compress = true;
                break;
            default:
                return false;
        }

        $length = strlen($data);
        $compressed = 0;

        // we compress only if the variable is a text (or json) and if it's longer than 100 characters, which is the
        // approximate threshold of when the encrypted data is smaller when compressed
        if ($compress && $length > 100) {
            $compressedData = gzcompress($data, 9);
            $compressedLength = strlen($compressedData);
            if ($compressedLength < $length) {
                $data = $compressedData;
                $compressed = 1;
            }
        }

        $data =
            self::IDENTIFIER . // identifier: 2 characters
            str_pad(base_convert($length, 10, 36), 6, "0", STR_PAD_LEFT) . // string length: 6 characters
            (int)array_search($type, self::DATA_TYPES) . // data type: 1 character
            $compressed . // compressed: 1 character
            $data;

        $result = self::encryptString($data, $key);

        return $hexEncode ? bin2hex($result) : $result;
    }

    /**
     * Decrypts and decodes the data, returning it in the original state, including its variable type.
     *
     * @param string $data The encrypted data.
     * @param string $key The decryption key.
     * @param bool $hexEncoded Whether the data is hex-encoded.
     * @return mixed The decrypted data in its original format or false on failure.
     */
    public static function decrypt(string $data, string $key, bool $hexEncoded = true): mixed
    {
        if (empty($data)) {
            return "";
        }

        try {
            $data = self::decryptString($hexEncoded ? @hex2bin($data) : $data, $key);
        } catch (Exception) {
            return false;
        }

        // check the identifier
        if (substr($data, 0, 2) != self::IDENTIFIER) {
            return false;
        }

        // get the length
        if (!($length = (int)base_convert(substr($data, 2, 6), 36, 10))) {
            return "";
        }

        // get the type
        $type = substr($data, 8, 1);

        if (!isset(self::DATA_TYPES[$type])) {
            return false;
        }

        // get the data and decompress if needed
        if (substr($data, 9, 1)) {
            $data = gzuncompress(substr($data, 10, $length));
        } else {
            $data = substr($data, 10, $length);
        }

        return match (self::DATA_TYPES[$type]) {
            "boolean" => $data == "1",
            "integer" => (int)$data,
            "double" => (double)$data,
            "array" => json_decode($data, true),
            "object" => json_decode($data),
            "NULL" => null,
            default => $data,
        };
    }

    /**
     * Encrypts a string and returns the encrypted string or false on error.
     *
     * @param string $string The string to encrypt.
     * @param string $key The encryption key.
     * @return bool|string The encrypted string or false on failure.
     */
    public static function encryptString(string $string, string $key): bool|string
    {
        if (empty($string) || empty($key) || !function_exists("openssl_encrypt")) {
            return false;
        }

        $iv = openssl_random_pseudo_bytes(16);
        return $iv . openssl_encrypt($string, "AES-256-CFB", $key, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Decrypts a string and returns the decrypted string or false on error.
     *
     * @param string $string The string to decrypt.
     * @param string $key The decryption key.
     * @return bool|string The decrypted string or false on failure.
     */
    public static function decryptString(string $string, string $key): bool|string
    {
        if (empty($string) || empty($key) || !function_exists("openssl_decrypt")) {
            return false;
        }

        return openssl_decrypt(substr($string, 16), "AES-256-CFB", $key, OPENSSL_RAW_DATA, substr($string, 0, 16));
    }

    /**
     * Generates a random password of the specified length using a mixture of characters.
     *
     * @param int $length The desired length of the password (default is 24).
     * @return string Returns the generated password as a string.
     */
    public static function generatePassword(int $length = 24): string
    {
        return self::generateRandomToken(
            $length,
            "1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM!@#%^&*()_-+="
        );
    }

    /**
     * Generates a random token composed of lowercase letters and numbers.
     *
     * @param int $length The desired length of the token (default is 32).
     * @return string Returns the generated random token as a string.
     */
    public static function generateRandomToken(int $length = 32,
                                               string $characters = "abcdefghijklmnopqrstuvwxyz1234567890"): string
    {
        $charactersLength = strlen($characters);
        $result = "";

        try {
            // use random_int() for cryptographically secure random numbers
            for ($i = 0; $i < $length; $i++) {
                $result .= $characters[random_int(0, $charactersLength - 1)];
            }
        } catch (\Throwable $e) {
            // if random_int fails, fall back to mt_rand() (not cryptographically secure)
            error_log("[Code::generateRandomToken] Failed to generate cryptographically secure token using " .
                "random_int; falling back to using mt_rand (insecure): " . $e->getMessage());

            for ($i = 0; $i < $length; $i++) {
                $result .= $characters[mt_rand(0, $charactersLength - 1)];
            }
        }

        return $result;
    }

    /**
     * Encodes a positive integer to a short string representation using a specified charset.
     *
     *  BASE_36_CHARSET - url safe; uses numbers and lowercase letters
     *  BASE_62_CHARSET - url safe; uses numbers, lowercase, and uppercase letters;
     *                    will produce shorter strings than BASE_36_CHARSET for numbers larger than 1,679,616
     *  BASE_92_CHARSET - not url safe; uses all printable characters except the ones used for separating data: | and ,
     *                    will produce shorter strings than BASE_62_CHARSET for numbers larger than 14,776,335
     *
     * @param string|int $number The positive integer to encode.
     * @param string $charset The charset to use for encoding (BASE_36_CHARSET, BASE_62_CHARSET, BASE_92_CHARSET).
     * @return string The encoded string.
     */
    public static function baseEncode(string|int $number, string $charset = Code::BASE_36_CHARSET): string
    {
        $number = trim($number);
        if (!is_numeric($number) || $number < 1) {
            return "";
        }

        $base = strlen($charset);
        $result = "";
        while ($number > 0) {
            $result = $charset[$number % $base] . $result;
            $number = intdiv($number, $base);
        }
        return $result;
    }

    /**
     * Decodes a string encoded with baseEncode.
     *
     * @param string $encoded The encoded string.
     * @param string $charset The charset used during encoding.
     * @return int The decoded integer.
     */
    public static function baseDecode(string $encoded, string $charset = Code::BASE_36_CHARSET): int
    {
        $base = strlen($charset);
        $length = strlen($encoded);
        $number = 0;

        for ($i = 0; $i < $length; $i++) {
            $number = $number * $base + strpos($charset, $encoded[$i]);
        }

        return $number;
    }

    /**
     * Converts encoded text to binary data.
     *
     * @param string $data The encoded text.
     * @return bool|string The decoded binary data.
     */
    public static function textToBinary(string $data): bool|string
    {
        return base64_decode(str_pad(strtr($data, "-_", "+/"), strlen($data) % 4, "=", STR_PAD_RIGHT));
    }

    /**
     * Converts binary data to an encoded string.
     *
     * @param mixed $data The binary data.
     * @return string The encoded string.
     */
    public static function binaryToText(mixed $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    /**
     * Returns an array of shuffled alphabet [0-F] in the format [originalCharacter => shuffledCharacter].
     *
     * @param string $salt The salt value used for shuffling.
     * @param bool $reverse Whether to reverse the shuffling.
     * @return array The shuffled alphabet mapping.
     */
    protected static function getShuffledAlphabet(string $salt, bool $reverse = false): array
    {
        $alphabet = $original = "0123456789abcdef";
        $length = 16;
        $result = [];

        for ($i = $length - 1, $v = 0, $p = 0; $i > 0; $i--, $v++) {
            $v %= strlen($salt);
            $p += $int = ord($salt[$v]);
            $j = ($int + $v + $p) % $i;
            $temp = $alphabet[$j];
            $alphabet[$j] = $alphabet[$i];
            $alphabet[$i] = $temp;
        }

        for ($i = 0; $i < $length; $i++) {
            if ($reverse) {
                $result[$alphabet[$i]] = $original[$i];
            } else {
                $result[$original[$i]] = $alphabet[$i];
            }
        }

        return $result;
    }
}
