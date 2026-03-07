<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public string $currentPassword = '',
        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public string $newPassword = '',
    ) {
    }
}
