<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum ThemeEnum: string
{
    case Light = 'light';
    case Dark = 'dark';
    case Auto = 'auto';
}
