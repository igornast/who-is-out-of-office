<?php

declare(strict_types=1);

namespace App\Shared\DTO\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Ramsey\Uuid\UuidInterface;

class LeaveRequestDTO
{
    public function __construct(
        public UuidInterface $id,
        public int $workDays,
        public LeaveRequestTypeDTO $leaveType,
        public LeaveRequestStatusEnum $status,
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public UserDTO $user,
        public ?bool $isAutoApproved = false,
        public ?string $comment = null,
        public ?UserDTO $approvedBy = null,
        public ?\DateTimeImmutable $createdAt = null,
    ) {
    }

    public static function fromEntity(LeaveRequest $leaveRequest): self
    {
        $approver = null;

        if (null !== $leaveRequest->approvedBy) {
            $approver = UserDTO::fromEntity($leaveRequest->approvedBy);
        }

        return new self(
            id: $leaveRequest->id,
            workDays: $leaveRequest->workDays,
            leaveType: LeaveRequestTypeDTO::fromEntity($leaveRequest->leaveType),
            status: $leaveRequest->status,
            startDate: $leaveRequest->startDate,
            endDate: $leaveRequest->endDate,
            user: UserDTO::fromEntity($leaveRequest->user),
            isAutoApproved: $leaveRequest->isAutoApproved,
            comment: $leaveRequest->comment,
            approvedBy: $approver,
            createdAt: $leaveRequest->getCreatedAt(),
        );
    }
}
