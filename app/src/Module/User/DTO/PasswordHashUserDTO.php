<?php

declare(strict_types=1);

namespace App\Module\User\DTO;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class PasswordHashUserDTO implements PasswordAuthenticatedUserInterface
{
    public function __construct(
        private string $email,
        private array $roles,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return null;
    }
}
