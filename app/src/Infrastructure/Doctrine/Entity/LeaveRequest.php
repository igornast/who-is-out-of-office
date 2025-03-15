<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use Ramsey\Uuid\UuidInterface;

class LeaveRequest
{
    use TimestampableTrait;

    public function __construct(
        public UuidInterface $id,
        public string $status,
        public int $workDays = 0,
        public string $comment,
        public LeaveType $leaveType,
        public User $user,
        public User $approvedBy,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
    ) {
        $this->initializeTimestamps();
    }
}
