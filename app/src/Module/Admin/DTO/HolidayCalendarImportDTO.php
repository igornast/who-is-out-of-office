<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class HolidayCalendarImportDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public string $country = '',
        #[Assert\NotBlank]
        #[Assert\Range(min: 2000, max: 2100)]
        public int $year = 0,
    ) {
    }
}
