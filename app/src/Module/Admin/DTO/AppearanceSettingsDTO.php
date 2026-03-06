<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;

class AppearanceSettingsDTO
{
    public function __construct(
        public ThemeEnum $theme = ThemeEnum::Auto,
        public PaletteEnum $palette = PaletteEnum::Teal,
    ) {
    }
}
