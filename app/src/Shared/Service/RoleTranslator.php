<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\Enum\RoleEnum;
use Symfony\Contracts\Translation\TranslatorInterface;

class RoleTranslator
{
    /**
     * @var array<string, string>
     */
    private array $roleMapping = [
        RoleEnum::Admin->value => 'role.admin',
        RoleEnum::Manager->value => 'role.manager',
        RoleEnum::User->value => 'role.user',
    ];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param string[] $roles
     */
    public function translate(array $roles): string
    {
        $translated = array_map(fn (string $role): string => $this->translator->trans(id: $this->roleMapping[$role] ?? $role, domain: 'admin'), $roles);

        return implode(', ', $translated);
    }
}
