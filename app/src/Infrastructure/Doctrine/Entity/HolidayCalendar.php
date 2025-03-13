<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class HolidayCalendar
{
    use TimestampableTrait;

    public function __construct(
        private ?int $id = null,
        private string $countryCode,
        private string $countryName,
        private Collection $holidays = new ArrayCollection(),
    ) {
        $this->initializeTimestamps();
    }
}
