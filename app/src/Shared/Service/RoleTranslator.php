<?php

declare(strict_types=1);

namespace App\Shared\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

class RoleTranslator
{
    private array $roleMapping = [
        'ROLE_ADMIN' => 'role.admin',
        'ROLE_MANAGER' => 'role.manager',
        'ROLE_USER' => 'role.user',
    ];

    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function translate(string|array $roles): string
    {
        if (is_array($roles)) {
            $translated = array_map(function (string $role): string {
                return $this->translator->trans(id: $this->roleMapping[$role] ?? $role, domain: 'admin');
            }, $roles);

            return implode(', ', $translated);
        }

        return $this->translator->trans(id: $this->roleMapping[$roles] ?? $roles, domain: 'admin');
    }
}
