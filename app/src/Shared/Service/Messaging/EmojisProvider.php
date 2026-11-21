<?php

declare(strict_types=1);

namespace App\Shared\Service\Messaging;

class EmojisProvider
{
    public const GENERIC_HOLIDAY_SHORTCODE = ':crossed_flags:';

    public static function getFlagEmojiCode(string $countryCode): string
    {
        if (!self::isValidCountryCode($countryCode)) {
            return '🏳';
        }

        $countryCode = strtoupper($countryCode);

        $emoji = '';
        foreach (str_split($countryCode) as $char) {
            $emoji .= mb_convert_encoding(
                '&#'.(127397 + ord($char)).';',
                'UTF-8',
                'HTML-ENTITIES'
            );
        }

        return $emoji;
    }

    public static function getFlagShortcode(string $countryCode): string
    {
        if (!self::isValidCountryCode($countryCode)) {
            return self::GENERIC_HOLIDAY_SHORTCODE;
        }

        return sprintf(':flag-%s:', strtolower($countryCode));
    }

    private static function isValidCountryCode(string $countryCode): bool
    {
        return 2 === strlen($countryCode) && ctype_alpha($countryCode);
    }
}
