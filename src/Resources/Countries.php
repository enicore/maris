<?php
/**
 * Enicore Maris.
 * Copyright 2024 Enicore Solutions.
 */
namespace Enicore\Maris\Resources;

use Locale;

/**
 * Manages a list of country codes and their corresponding names. Uses PHP's Intl extension for locale-aware country
 * names.
 *
 * @package Enicore\Maris
 */
class Countries
{
    // ISO 3166-1 alpha-2 country codes
    protected static array $countryCodes = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AN', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ', 'BA',
        'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BV', 'BW',
        'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV', 'CW',
        'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'EU', 'FI',
        'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ',
        'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ',
        'IR', 'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ',
        'LA', 'LB', 'LC', 'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH',
        'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA', 'NC',
        'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL',
        'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE',
        'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD',
        'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US',
        'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'XK', 'YE', 'YT', 'ZA', 'ZM', 'ZW'
    ];

    /**
     * Gets the full list of countries in the specified language.
     *
     * @param string $language The language code (e.g., "en" for English, "es" for Spanish).
     * @return array An associative array of country codes and their corresponding country names.
     */
    public static function getCountries(array $codes = [], string $language = 'en'): array
    {
        $codes = empty($codes) ? self::$countryCodes : array_map('strtoupper', $codes);
        $result = [];

        foreach ($codes as $code) {
            if (in_array($code, self::$countryCodes, true) &&
                ($name = Locale::getDisplayRegion('-' . $code, $language))
            ) {
                $result[$code] = $name;
            }
        }

        return $result;
    }

    /**
     * Returns the array of ISO 3166-1 alpha-2 country codes.
     *
     * @return array
     */
    public static function getCodes(): array
    {
        return self::$countryCodes;
    }

    /**
     * Gets the name of a country by its code in the specified language.
     *
     * @param string $code
     * @param string $language The language code (default is "en").
     * @return string The localized country name if it exists, or an empty string if it doesn't.
     */
    public static function getName(string $code, string $language = 'en'): string
    {
        if (empty($code)) {
            return '';
        }

        $code = strtoupper($code);
        $result = Locale::getDisplayRegion('-' . $code, $language);

        return $result == $code ? '' : $result;
    }

    /**
     * Checks if a country exists based on its code.
     *
     * @param string $countryCode The ISO 3166-1 alpha-2 country code (e.g., "US", "ES").
     * @return bool True if the country exists, false otherwise.
     */
    public static function codeExists(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), self::$countryCodes);
    }
}