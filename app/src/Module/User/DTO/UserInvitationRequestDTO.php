<?php

declare(strict_types=1);

namespace App\Module\User\DTO;

final class UserInvitationRequestDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $password,
        public ?\DateTimeImmutable $birthdate = null,
    ) {
    }
}
