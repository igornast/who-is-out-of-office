<?php

declare(strict_types=1);

namespace App\Module\Settings;

use App\Module\Settings\Exception\InvalidAppSettingTypeException;
use App\Module\Settings\UseCase\Command\UpdateAppSettingsValueCommandHandler;
use App\Module\Settings\UseCase\Query\GetAllAppSettingsQueryHandler;
use App\Module\Settings\UseCase\Query\GetAppSettingsValueQueryHandler;
use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Shared\Enum\AppSettingsEnum;
use App\Shared\Facade\AppSettingsFacadeInterface;

final class SettingsFacade implements AppSettingsFacadeInterface
{
    public function __construct(
        private readonly GetAppSettingsValueQueryHandler $appSettingValueHandler,
        private readonly GetAllAppSettingsQueryHandler $getAllAppSettingsQueryHandler,
        private readonly UpdateAppSettingsValueCommandHandler $updateAppSettingsValueCommandHandler,
    ) {
    }

    public function isAutoApprove(): bool
    {
        $value = $this->appSettingValueHandler->handle(AppSettingsEnum::AUTO_APPROVE);

        if (!is_bool($value)) {
            throw new InvalidAppSettingTypeException(expected: 'bool', settingsEnum: AppSettingsEnum::AUTO_APPROVE);

        }

        return $value;
    }

    public function autoApproveDelay(): int
    {
        $value = $this->appSettingValueHandler->handle(AppSettingsEnum::AUTO_APPROVE_DELAY);

        if (!is_int($value)) {
            throw new InvalidAppSettingTypeException(expected: 'int', settingsEnum: AppSettingsEnum::AUTO_APPROVE_DELAY);
        }

        return $value;
    }

    public function defaultAnnualAllowance(): int
    {
        $value = $this->appSettingValueHandler->handle(AppSettingsEnum::DEFAULT_ANNUAL_ALLOWANCE);

        if (!is_int($value)) {
            throw new InvalidAppSettingTypeException(expected: 'int', settingsEnum: AppSettingsEnum::DEFAULT_ANNUAL_ALLOWANCE);
        }

        return $value;
    }

    public function minNoticeDays(): int
    {
        $value = $this->appSettingValueHandler->handle(AppSettingsEnum::MIN_NOTICE_DAYS);

        if (!is_int($value)) {
            throw new InvalidAppSettingTypeException(expected: 'int', settingsEnum: AppSettingsEnum::MIN_NOTICE_DAYS);
        }

        return $value;
    }

    public function maxConsecutiveDays(): int
    {
        $value = $this->appSettingValueHandler->handle(AppSettingsEnum::MAX_CONSECUTIVE_DAYS);

        if (!is_int($value)) {
            throw new InvalidAppSettingTypeException(expected: 'int', settingsEnum: AppSettingsEnum::MAX_CONSECUTIVE_DAYS);
        }

        return $value;
    }

    public function skipWeekendHolidays(): bool
    {
        $value = $this->appSettingValueHandler->handle(AppSettingsEnum::SKIP_WEEKEND_HOLIDAYS);

        if (null === $value) {
            return false;
        }

        if (!is_bool($value)) {
            throw new InvalidAppSettingTypeException(expected: 'bool', settingsEnum: AppSettingsEnum::SKIP_WEEKEND_HOLIDAYS);
        }

        return $value;
    }

    public function getAllSettings(): AppSettingsDTO
    {
        return $this->getAllAppSettingsQueryHandler->handle();
    }

    public function updateAllSettings(AppSettingsDTO $settingsDTO): void
    {
        $this->updateAppSettingsValueCommandHandler->handle($settingsDTO);
    }
}
