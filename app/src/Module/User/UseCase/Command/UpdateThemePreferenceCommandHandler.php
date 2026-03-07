<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;

class UpdateThemePreferenceCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function handle(string $userId, ThemeEnum $theme, PaletteEnum $palette): void
    {
        $this->userRepository->updateThemePreference($userId, $theme->value, $palette->value);
    }
}
