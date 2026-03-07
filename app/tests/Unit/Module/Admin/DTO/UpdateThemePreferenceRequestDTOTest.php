<?php

declare(strict_types=1);

use App\Module\Admin\DTO\UpdateThemePreferenceRequestDTO;
use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;

it('uses provided theme and palette enums', function () {
    $dto = new UpdateThemePreferenceRequestDTO(
        theme: ThemeEnum::Dark,
        palette: PaletteEnum::Sage,
    );

    expect($dto->theme)->toBe(ThemeEnum::Dark)
        ->and($dto->palette)->toBe(PaletteEnum::Sage);
});

it('defaults theme to auto', function () {
    $dto = new UpdateThemePreferenceRequestDTO();

    expect($dto->theme)->toBe(ThemeEnum::Auto);
});

it('defaults palette to teal', function () {
    $dto = new UpdateThemePreferenceRequestDTO();

    expect($dto->palette)->toBe(PaletteEnum::Teal);
});
