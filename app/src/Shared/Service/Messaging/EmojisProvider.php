<?php

declare(strict_types=1);

namespace App\Shared\Service\Messaging;

use App\Shared\Enum\LeaveRequestTypeEnum;

class EmojisProvider
{
    public static function getFlagEmojiCode(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);

        if (2 !== strlen($countryCode) || !ctype_alpha($countryCode)) {
            return '🏳';
        }

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

    public static function getLeaveTypeEmoji(LeaveRequestTypeEnum $type): string
    {
        return  match ($type) {
            LeaveRequestTypeEnum::Vacation => '🌴',
            LeaveRequestTypeEnum::SickLeave => '🤒',
        };
    }
}
