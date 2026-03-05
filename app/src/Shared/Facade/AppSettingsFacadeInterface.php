<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\Settings\AppSettingsDTO;

interface AppSettingsFacadeInterface
{
    public function isAutoApprove(): bool;

    public function autoApproveDelay(): int;

    public function defaultAnnualAllowance(): int;

    public function minNoticeDays(): int;

    public function maxConsecutiveDays(): int;

    public function skipWeekendHolidays(): bool;

    public function getAllSettings(): AppSettingsDTO;

    public function updateAllSettings(AppSettingsDTO $settingsDTO): void;
}
