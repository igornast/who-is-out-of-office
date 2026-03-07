<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;

class UpdateThemePreferenceRequestDTO
{
    public function __construct(
        public readonly ThemeEnum $theme = ThemeEnum::Auto,
        public readonly PaletteEnum $palette = PaletteEnum::Teal,
    ) {
    }
}
