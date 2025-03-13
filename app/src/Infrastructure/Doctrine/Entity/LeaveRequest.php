<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;

class LeaveRequest
{
    use TimestampableTrait;

    public function __construct(
        private ?int $id = null,
        private string $status = '',
        private int $workDays = 0,
        private ?string $comment = null,
        private ?LeaveType $leaveType = null,
        private ?User $user = null,
        private ?User $approvedBy = null,
        private \DateTimeInterface $startDate,
        private \DateTimeInterface $endDate,
    ) {
        $this->initializeTimestamps();
    }
}
