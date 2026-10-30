<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Ramsey\Uuid\UuidInterface;

class LeavePeriod
{
    use TimestampableTrait;

    public function __construct(
        public int $allowanceGranted,
        public int $openingBalance,
        public UuidInterface $id,
        public User $user,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public ?int $closingBalance = null,
        public ?\DateTimeImmutable $closedAt = null,
    ) {
        $this->initializeTimestamps();
    }
}
