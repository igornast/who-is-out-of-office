<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Ramsey\Uuid\UuidInterface;

class LeaveRequest
{
    use TimestampableTrait;

    public function __construct(
        public UuidInterface $id,
        public User $user,
        public LeaveRequestStatusEnum $status,
        public LeaveRequestType $leaveType,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public int $workDays = 0,
        public ?bool $isAutoApproved = false,
        public ?string $comment = null,
        public ?User $approvedBy = null,
    ) {
        $this->initializeTimestamps();
    }
}
