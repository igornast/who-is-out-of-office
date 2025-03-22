<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\LeaveRequestTypeEnum;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class LeaveRequest
{
    use TimestampableTrait;

    public function __construct(
        public UuidInterface $id,
        public UserInterface|User $user,
        public LeaveRequestStatusEnum $status,
        public LeaveRequestTypeEnum $leaveType,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public int $workDays = 0,
        public ?User $approvedBy = null,
        public ?string $comment = null,
    ) {
        $this->initializeTimestamps();
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }
}
