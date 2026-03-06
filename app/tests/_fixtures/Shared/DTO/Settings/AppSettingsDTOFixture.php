<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\Settings;

use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Tests\_fixtures\FixtureInterface;

class AppSettingsDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): AppSettingsDTO
    {
        return new AppSettingsDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        return [
            'autoApprove' => true,
            'autoApproveDelay' => 5,
            'defaultAnnualAllowance' => 24,
            'minNoticeDays' => 3,
            'maxConsecutiveDays' => 10,
            'skipWeekendHolidays' => false,
        ];
    }
}
