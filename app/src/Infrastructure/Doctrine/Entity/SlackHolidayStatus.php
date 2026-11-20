<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Ramsey\Uuid\UuidInterface;

class SlackHolidayStatus
{
    use TimestampableTrait;

    public function __construct(
        public string $statusText,
        public UuidInterface $id,
        public User $user,
        public \DateTimeImmutable $date,
        public ?Holiday $holiday = null,
    ) {
        $this->initializeTimestamps();
    }
}
