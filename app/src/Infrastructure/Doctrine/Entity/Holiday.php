<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Ramsey\Uuid\UuidInterface;

class Holiday implements \Stringable
{
    use TimestampableTrait;

    /**
     * @param string[]|null $counties
     */
    public function __construct(
        public UuidInterface $id,
        public string $description,
        public \DateTimeImmutable $date,
        public HolidayCalendar $holidayCalendar,
        public bool $isGlobal = true,
        public ?array $counties = null,
    ) {
        $this->initializeTimestamps();
    }

    public function __toString(): string
    {
        return sprintf('%s — %s', $this->date->format('M d, Y'), $this->description);
    }
}
