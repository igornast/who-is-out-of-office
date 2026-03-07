<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\UpdateThemePreferenceCommandHandler;
use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);

    $this->handler = new UpdateThemePreferenceCommandHandler(
        userRepository: $this->userRepository,
    );
});

it('persists theme and palette values to repository', function () {
    $this->userRepository
        ->expects('updateThemePreference')
        ->once()
        ->with('user-1', 'dark', 'sage');

    $this->handler->handle('user-1', ThemeEnum::Dark, PaletteEnum::Sage);
});

it('persists all valid theme values', function (ThemeEnum $theme) {
    $this->userRepository
        ->expects('updateThemePreference')
        ->once()
        ->with('user-1', $theme->value, 'teal');

    $this->handler->handle('user-1', $theme, PaletteEnum::Teal);
})->with([ThemeEnum::Light, ThemeEnum::Dark, ThemeEnum::Auto]);

it('persists all valid palette values', function (PaletteEnum $palette) {
    $this->userRepository
        ->expects('updateThemePreference')
        ->once()
        ->with('user-1', 'auto', $palette->value);

    $this->handler->handle('user-1', ThemeEnum::Auto, $palette);
})->with([PaletteEnum::Teal, PaletteEnum::Sage, PaletteEnum::Sunset, PaletteEnum::Lavender]);
