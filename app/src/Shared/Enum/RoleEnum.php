<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum RoleEnum: string
{
    case Admin = 'ROLE_ADMIN';
    case Manager = 'ROLE_MANAGER';
    case User = 'ROLE_USER';

    public function translationKey(): string
    {
        return match ($this) {
            self::Admin => 'role.admin',
            self::Manager => 'role.manager',
            self::User => 'role.user',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function assignableChoices(): array
    {
        $choices = [];

        foreach ([self::Admin, self::Manager] as $role) {
            $choices[$role->translationKey()] = $role->value;
        }

        return $choices;
    }

    /**
     * @return array<string, RoleEnum>
     */
    public static function getChoices(): array
    {
        return [
            'Admin' => self::Admin,
            'Manager' => self::Manager,
            'User' => self::User,
        ];
    }
}
