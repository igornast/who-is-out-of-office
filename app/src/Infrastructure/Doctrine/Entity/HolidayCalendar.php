<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\UuidInterface;

class HolidayCalendar
{
    use TimestampableTrait;

    /**
     * @param Collection<int, Holiday> $holidays
     */
    public function __construct(
        public UuidInterface $id,
        public string $countryCode,
        public string $countryName,
        public Collection $holidays = new ArrayCollection(),
    ) {
        $this->initializeTimestamps();
    }
}
