<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class TwoFactorSetupDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public string $currentPassword = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: 6, max: 6)]
        #[Assert\Regex(pattern: '/^\d{6}$/', message: 'Enter a 6-digit code')]
        public string $verificationCode = '',
    ) {
    }
}
