<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;

class UpdateLeaveRequestWithInteractiveNotificationCommandHandler
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    public function handle(InteractiveNotificationDTO $notificationDTO): ?LeaveRequestDTO
    {
        $leaveRequestDTO = $this->leaveRequestFacade->getById($notificationDTO->identifier);

        if (null === $leaveRequestDTO) {
            throw new \RuntimeException(sprintf('Leave request with id "%s" not found', $notificationDTO->identifier));
        }

        if (true === $leaveRequestDTO->isAutoApproved) {
            return $leaveRequestDTO;
        }

        if ($this->isAlreadyWithdrawn($leaveRequestDTO)) {
            return null;
        }

        $approvedByUserDTO = !is_null($notificationDTO->memberId)
            ? $this->userFacade->getUserBySlackMemberId($notificationDTO->memberId)
            : null;

        $leaveRequestDTO->status = $notificationDTO->status;
        $leaveRequestDTO->approvedBy = $approvedByUserDTO;

        $transitionApplied = $this->leaveRequestFacade->updateAndRestoreBalanceIfNeeded(
            $leaveRequestDTO,
            BalanceLedgerSourceEnum::Slack,
            $approvedByUserDTO?->id,
        );

        if (!$transitionApplied) {
            return null;
        }

        return $leaveRequestDTO;
    }

    public function isAlreadyWithdrawn(LeaveRequestDTO $leaveRequestDTO): bool
    {
        return in_array($leaveRequestDTO->status, [LeaveRequestStatusEnum::Rejected, LeaveRequestStatusEnum::Cancelled, LeaveRequestStatusEnum::Withdrawn], true);
    }
}
