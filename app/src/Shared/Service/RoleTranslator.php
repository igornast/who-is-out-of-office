<?php

declare(strict_types=1);

namespace App\Shared\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

class RoleTranslator
{
    /**
     * @var array<string, string>
     */
    private array $roleMapping = [
        'ROLE_ADMIN' => 'role.admin',
        'ROLE_MANAGER' => 'role.manager',
        'ROLE_USER' => 'role.user',
    ];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param string[] $roles
     */
    public function translate(array $roles): string
    {
        $translated = array_map(function (string $role): string {
            return $this->translator->trans(id: $this->roleMapping[$role] ?? $role, domain: 'admin');
        }, $roles);

        return implode(', ', $translated);
    }
}
