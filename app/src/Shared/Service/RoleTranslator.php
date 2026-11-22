<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\Enum\RoleEnum;
use Symfony\Contracts\Translation\TranslatorInterface;

class RoleTranslator
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param string[] $roles
     */
    public function translate(array $roles): string
    {
        $translated = array_map(fn (string $role): string => $this->translator->trans(id: RoleEnum::tryFrom($role)?->translationKey() ?? $role, domain: 'admin'), $roles);

        return implode(', ', $translated);
    }
}
