<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Resources;

use DateTime;
use DateTimeZone;
use Exception;
use IntlTimeZone;

/**
 * Manages a list of time zones and their corresponding names. Uses PHP's Intl and DateTimeZone extensions for
 * locale-aware time zone names and offsets.
 *
 * @package Enicore\Maris
 */
class Timezones
{
    /**
     * Default timezone name formats used for countries with a single timezone or multiple timezones
     */
    protected static string $singleTimezoneFormat = '%country (%offset)';
    protected static string $multipleTimezoneFormat = '%country / %tz_generic_location (%offset)';

    /**
     * Includes all timezones returned by DateTimeZone::listIdentifiers() grouped by country codes.
     */
    protected static array $timezones = [];

    /**
     * DateTimeZone::listIdentifiers() provides a comprehensive list of time zone identifiers, many of which are
     * redundant as they refer to the same underlying time zone. For example, Argentina has 12 identifiers for various
     * cities, but they all correspond to the same time zone offset.
     *
     * This array defines a curated list of essential time zones for countries that have multiple zones. By default, the
     * getTimezones() method will include only these essential time zones for countries with multiple time zones. To
     * retrieve the full list of all available time zones, set the $extended parameter to true when calling
     * getTimezones().
     */
    protected static array $essentialTimezones = [
        'AQ' => ['Antarctica/Troll', 'Antarctica/Syowa', 'Antarctica/Mawson', 'Antarctica/Vostok', 'Antarctica/Davis',
            'Antarctica/Casey', 'Antarctica/DumontDUrville', 'Antarctica/McMurdo', 'Antarctica/Palmer'],
        'AR' => ['America/Argentina/Buenos_Aires'],
        'AU' => ['Australia/Perth', 'Australia/Eucla', 'Australia/Darwin', 'Australia/Brisbane', 'Australia/Adelaide',
            'Antarctica/Macquarie', 'Australia/Lord_Howe', 'Australia/Melbourne', 'Australia/Sydney'],
        'BR' => ['America/Noronha', 'America/Araguaina', 'America/Sao_Paulo', 'America/Boa_Vista', 'America/Eirunepe'],
        'CA' => ['America/St_Johns', 'America/Blanc-Sablon', 'America/Atikokan', 'America/Toronto',
            'America/Rankin_Inlet', 'America/Cambridge_Bay', 'America/Edmonton', 'America/Whitehorse',
            'America/Vancouver'],
        'CD' => ['Africa/Kinshasa', 'Africa/Lubumbashi'],
        'CL' => ['America/Punta_Arenas', 'Pacific/Easter'],
        'CN' => ['Asia/Urumqi', 'Asia/Shanghai'],
        'CY' => ['Asia/Famagusta'],
        'DE' => ['Europe/Berlin'],
        'EC' => ['America/Guayaquil', 'Pacific/Galapagos'],
        'ES' => ['Atlantic/Canary', 'Europe/Madrid'],
        'FM' => ['Pacific/Chuuk', 'Pacific/Kosrae'],
        'GL' => ['America/Danmarkshavn', 'America/Nuuk', 'America/Thule'],
        'ID' => ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'],
        'KI' => ['Pacific/Tarawa', 'Pacific/Kanton', 'Pacific/Kiritimati'],
        'KZ' => ['Asia/Almaty'],
        'MH' => ['Pacific/Kwajalein'],
        'MN' => ['Asia/Hovd', 'Asia/Ulaanbaatar'],
        'MX' => ['America/Cancun', 'America/Bahia_Banderas', 'America/Chihuahua', 'America/Ciudad_Juarez',
            'America/Hermosillo', 'America/Tijuana'],
        'MY' => ['Asia/Kuala_Lumpur'],
        'NZ' => ['Pacific/Auckland', 'Pacific/Chatham'],
        'PF' => ['Pacific/Gambier', 'Pacific/Marquesas', 'Pacific/Tahiti'],
        'PG' => ['Pacific/Port_Moresby', 'Pacific/Bougainville'],
        'PS' => ['Asia/Gaza'],
        'PT' => ['Atlantic/Madeira', 'Europe/Lisbon', 'Atlantic/Azores'],
        'RU' => ['Europe/Kaliningrad', 'Europe/Moscow', 'Europe/Samara', 'Asia/Yekaterinburg', 'Asia/Omsk',
            'Asia/Krasnoyarsk', 'Asia/Irkutsk', 'Asia/Yakutsk', 'Asia/Vladivostok', 'Asia/Magadan', 'Asia/Anadyr'],
        'UA' => ['Europe/Kyiv', 'Europe/Simferopol'],
        'UM' => ['Pacific/Wake', 'Pacific/Midway'],
        'US' => ['America/Detroit', 'America/Indiana/Indianapolis', 'America/Kentucky/Louisville', 'America/New_York',
            'America/Chicago', 'America/Boise', 'America/Denver', 'America/Phoenix', 'America/Los_Angeles',
            'America/Anchorage', 'America/Adak', 'Pacific/Honolulu'],
        'UZ' => ['Asia/Samarkand'],
    ];

    /**
     * Returns localized timezone names formatted based on the provided settings.
     *
     * @param bool $extended If true, includes extended timezone data. If false, includes the curated list of
     *      non-repeating entries for countries that have multiple time zones. Default: false.
     * @param string $language Language code for localization (e.g., 'en'). Default: 'en'.
     * @param string|null $singleTimezoneFormat Format for single-timezone countries.
     *      Default: '%country (%offset)'.
     * @param string|null $multipleTimezoneFormat Format for multi-timezone countries.
     *      Default: '%country / %tz_generic_location (%offset)'.
     * @return array Associative array with timezone IDs as keys and localized names as values.
     */
    public static function getTimezones(bool $extended = false,
                                        string $language = 'en',
                                        string $singleTimezoneFormat = null,
                                        string $multipleTimezoneFormat = null): array
    {
        $result = [];

        foreach (self::getTimezoneData($extended) as $countryCode => $timezones) {
            foreach ($timezones as $timezone) {
                $result[$timezone] = self::createLocalizedName(
                    $timezone,
                    $language,
                    count($timezones) == 1 ?
                        ($singleTimezoneFormat ?? static::$singleTimezoneFormat) :
                        ($multipleTimezoneFormat ?? static::$multipleTimezoneFormat),
                    $countryCode
                );
            }
        }

        asort($result);

        return $result;
    }

    /**
     * Returns the localized name of a timezone formatted using the specified format.
     *
     * @param string $timezone The timezone identifier (e.g., 'Europe/Berlin').
     * @param string $language The language code for localization (default: 'en').
     * @param string|null $format The format string for the timezone name (default: multipleTimezoneFormat).
     * @return string The localized and formatted timezone name, or the raw timezone identifier if formatting fails.
     */
    public static function getName(string $timezone, string $language = 'en', string $format = null): string
    {
        try {
            if (($location = (new DateTimeZone($timezone))->getLocation()) &&
                !empty($location['country_code'])
            ) {
                return self::createLocalizedName(
                    $timezone,
                    $language,
                    $format ?? self::$multipleTimezoneFormat,
                    $location['country_code']
                );
            }
        } catch (Exception) {}

        return $timezone;
    }

    /**
     * Gets the current UTC offset for a given time zone.
     *
     * @param string $timezone The time zone identifier (e.g., "Europe/London").
     * @return string The offset in hours and minutes (e.g., "+01:00").
     */
    public static function getUtcOffset(string $timezone): string
    {
        try {
            $dtz = new DateTimeZone($timezone);
            $offset = $dtz->getOffset(new DateTime('now', $dtz));

            return sprintf('%+03d:%02d', intdiv($offset, 3600), abs($offset % 3600) / 60);
        } catch (Exception) {
            return 'Invalid Time Zone';
        }
    }

    /**
     * Checks if a time zone exists.
     *
     * @param string $timezone The time zone identifier to check (e.g., "Europe/London").
     * @return bool True if the time zone exists, false otherwise.
     */
    public static function timeZoneExists(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Converts a given date and time from one timezone to another.
     *
     * @param string $dateTime The original date and time (e.g., '2024-12-14 15:00:00').
     * @param string $fromTimezone The source timezone (e.g., 'America/New_York').
     * @param string $toTimezone The target timezone (e.g., 'Europe/Berlin').
     * @return string|null The converted time formatted as 'Y-m-d H:i:s', or null on failure.
     */
    public static function convertTime(string $dateTime, string $fromTimezone, string $toTimezone): ?string
    {
        try {
            $fromZone = new DateTimeZone($fromTimezone);
            $toZone = new DateTimeZone($toTimezone);
            $date = new DateTime($dateTime, $fromZone);
            $date->setTimezone($toZone);
            return $date->format('Y-m-d H:i:s');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Retrieves the current date and time in a specific timezone.
     *
     * @param string $timezone The timezone identifier (e.g., 'Europe/Berlin').
     * @return string|null The current time formatted as 'Y-m-d H:i:s', or null if the timezone is invalid.
     */
    public static function getCurrentTime(string $timezone): ?string
    {
        try {
            $dateTime = new DateTime('now', new DateTimeZone($timezone));
            return $dateTime->format('Y-m-d H:i:s');
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Retrieves all timezones for a given country code.
     *
     * @param string $countryCode The ISO country code (e.g., 'US').
     * @param bool $extended If true, includes all available timezones. Default: false.
     * @return array List of timezone identifiers, or an empty array if the country code is invalid.
     */
    public static function getTimezonesByCountry(string $countryCode, bool $extended = false): array
    {
        $timezoneData = self::getTimezoneData($extended);
        return $timezoneData[$countryCode] ?? [];
    }

    /**
     * Retrieves timezone data grouped by country codes.
     *
     * @param bool $extended If true, includes all timezones. If false, includes only essential timezones.
     * @return array An associative array where keys are country codes and values are lists of timezone identifiers.
     */
    public static function getTimezoneData(bool $extended = false): array
    {
        if (empty(self::$timezones)) {
            foreach (DateTimeZone::listIdentifiers() as $id) {
                try {
                    $tz = new DateTimeZone($id);
                    if (($location = $tz->getLocation()) && isset($location['country_code'])) {
                        self::$timezones[$location['country_code']] ??= [];
                        self::$timezones[$location['country_code']][] = $id;
                    }
                } catch (Exception) {}
            }
        }

        return $extended ? self::$timezones : array_merge(self::$timezones, self::$essentialTimezones);
    }

    /**
     * Helper function for creating a localized and formatted name for a timezone.
     *
     * @param string $timezone The timezone identifier (e.g., 'Europe/Berlin').
     * @param string $language The language code for localization (e.g., 'en').
     * @param string $format The format string with placeholders for timezone details.
     * @param string $countryCode The ISO country code associated with the timezone.
     * @return string The localized and formatted timezone name, or the raw timezone identifier if localization fails.
     */
    protected static function createLocalizedName(string $timezone, string $language, string $format,
                                                 string $countryCode): string
    {
        if (!($itz = IntlTimeZone::createTimeZone($timezone)) ||
            !($countryName = Countries::getName($countryCode, $language))
        ) {
            return $timezone;
        }

        $result = str_replace('%country', $countryName, $format);
        $result = str_replace('%offset', self::getUtcOffset($timezone), $result);

        $keys = [
            'tz_short' => IntlTimeZone::DISPLAY_SHORT,
            'tz_long' => IntlTimeZone::DISPLAY_LONG,
            'tz_short_generic' => IntlTimeZone::DISPLAY_SHORT_GENERIC,
            'tz_long_generic' => IntlTimeZone::DISPLAY_LONG_GENERIC,
            'tz_short_gmt' => IntlTimeZone::DISPLAY_SHORT_GMT,
            'tz_long_gmt' => IntlTimeZone::DISPLAY_LONG_GMT,
            'tz_short_commonly_used' => IntlTimeZone::DISPLAY_SHORT_COMMONLY_USED,
            'tz_generic_location' => IntlTimeZone::DISPLAY_GENERIC_LOCATION,
        ];

        foreach ($keys as $key => $value) {
            $result = str_replace("%$key", $itz->getDisplayName(false, $value, $language), $result);
        }

        return $result;
    }
}
