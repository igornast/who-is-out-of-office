<?php

declare(strict_types=1);

namespace App\Module\User\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 12)]
        public string $password = '',
    ) {
    }
}
