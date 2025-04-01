<?php

declare(strict_types=1);

namespace App\Shared\DTO;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\LeaveRequestTypeEnum;

class LeaveRequestDTO
{
    public function __construct(
        public string $id,
        public int $workDays,
        public LeaveRequestStatusEnum $status,
        public LeaveRequestTypeEnum $leaveType,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public UserDTO $user,
        public ?UserDTO $approvedBy = null,
        public ?string $comment = null,
    ) {
    }

    public static function fromEntity(LeaveRequest $leaveRequest): self
    {
        $approver = null;

        if (null !== $leaveRequest->approvedBy) {
            $approver = UserDTO::fromEntity($leaveRequest->approvedBy);
        }

        return new self(
            id: $leaveRequest->id->toString(),
            workDays: $leaveRequest->workDays,
            status: $leaveRequest->status,
            leaveType: $leaveRequest->leaveType,
            startDate: $leaveRequest->startDate,
            endDate: $leaveRequest->endDate,
            user: UserDTO::fromEntity($leaveRequest->user),
            approvedBy: $approver,
            comment: $leaveRequest->comment,
        );
    }
}
