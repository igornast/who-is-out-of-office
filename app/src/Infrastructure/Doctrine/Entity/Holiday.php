<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Ramsey\Uuid\UuidInterface;

class Holiday implements \Stringable
{
    use TimestampableTrait;

    public function __construct(
        public UuidInterface $id,
        public string $description,
        public \DateTimeImmutable $date,
        public HolidayCalendar $holidayCalendar,
    ) {
        $this->initializeTimestamps();
    }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->date->format('M d, Y'), $this->description);
    }
}
