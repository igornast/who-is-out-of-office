<?php

declare(strict_types=1);

namespace App\Shared\Service;

use Symfony\Contracts\Translation\TranslatorInterface;

class UserMessagingTranslator
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function translate(string $id): string
    {
        return $this->translator->trans(id: $id, domain: 'users_messaging');
    }
}
