<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;

class Holiday
{
    use TimestampableTrait;

    public function __construct(
        private ?int $id = null,
        private string $description,
        private \DateTimeImmutable $date,
        private HolidayCalendar $holidayCalendar,
    ) {
        $this->initializeTimestamps();
    }
}
