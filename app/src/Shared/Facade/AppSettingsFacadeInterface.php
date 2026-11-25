<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Shared\Exception\AppSettingsWriteException;

interface AppSettingsFacadeInterface
{
    public function isAutoApprove(): bool;

    public function autoApproveDelay(): int;

    public function defaultAnnualAllowance(): int;

    public function minNoticeDays(): int;

    public function maxConsecutiveDays(): int;

    public function skipWeekendHolidays(): bool;

    public function isSlackStatusSyncEnabled(): bool;

    public function organizationName(): string;

    public function weeklyDigestCronExpression(): string;

    public function weeklyDigestTimezone(): string;

    public function getAllSettings(): AppSettingsDTO;

    /**
     * @throws AppSettingsWriteException
     */
    public function updateAllSettings(AppSettingsDTO $settingsDTO): void;
}
