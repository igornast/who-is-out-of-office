<?php

declare(strict_types=1);

namespace App\Shared\DTO;

class UserDTO
{

    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public array $roles,
        public int $annualLeaveAllowance
    ) {}
}