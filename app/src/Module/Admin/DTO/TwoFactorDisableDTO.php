<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorDisableDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public string $password = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: 6, max: 6)]
        #[Assert\Regex(pattern: '/^\d{6}$/')]
        public string $totpCode = '',
    ) {
    }
}
