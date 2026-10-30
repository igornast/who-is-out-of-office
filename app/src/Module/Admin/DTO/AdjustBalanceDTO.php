<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AdjustBalanceDTO
{
    public function __construct(
        #[Assert\NotEqualTo(0, message: 'user.adjust_balance.validation.non_zero')]
        #[Assert\Range(min: -365, max: 365, notInRangeMessage: 'user.adjust_balance.validation.range')]
        public int $delta = 0,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $reason = '',
    ) {
    }
}
