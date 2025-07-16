<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use Symfony\Component\Validator\Constraints\Date;

class LeaveRequestCalculateRequestDTO
{
    public function __construct(
        #[Date]
        public string $startDate,
        #[Date]
        public string $endDate,
    ) {

    }
}
