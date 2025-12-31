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

final class AppSettingsFacade implements AppSettingsFacadeInterface
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

    public function getAllSettings(): AppSettingsDTO
    {
        return $this->getAllAppSettingsQueryHandler->handle();
    }

    public function updateAllSettings(AppSettingsDTO $settingsDTO): void
    {
        $this->updateAppSettingsValueCommandHandler->handle($settingsDTO);
    }
}
