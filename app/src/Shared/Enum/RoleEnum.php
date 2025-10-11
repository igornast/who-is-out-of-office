<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum RoleEnum: string
{
    case Admin = 'ROLE_ADMIN';
    case Manager = 'ROLE_MANAGER';
    case User = 'ROLE_USER';

    public static function getChoices(): array
    {
        return [
            'Admin' => self::Admin,
            'Manager' => self::Manager,
            'User' => self::User,
        ];
    }
}
