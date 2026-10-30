<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Command;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;

class UpdateLeaveRequestWithBalanceCommandHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly UpdateLeaveRequestCommandHandler $updateLeaveRequestHandler,
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    public function handle(
        LeaveRequestDTO $leaveRequestDTO,
        BalanceLedgerSourceEnum $source = BalanceLedgerSourceEnum::App,
        ?string $actorUserId = null,
    ): bool {
        $ownsTransaction = !$this->leaveRequestRepository->isTransactionActive();

        if ($ownsTransaction) {
            $this->leaveRequestRepository->beginTransaction();
        }

        try {
            $current = $this->leaveRequestRepository->findByIdForUpdate($leaveRequestDTO->id->toString());

            if (null === $current) {
                throw new \DomainException(sprintf('Leave request "%s" not found.', $leaveRequestDTO->id->toString()));
            }

            if (!$this->canTransition($current->status, $leaveRequestDTO->status)) {
                if ($ownsTransaction) {
                    $this->leaveRequestRepository->commit();
                }

                return false;
            }

            $this->updateLeaveRequestHandler->handle($leaveRequestDTO);

            if ($this->restoresBalance($leaveRequestDTO->status)
                && $this->userFacade->shouldRestoreBalanceForLeaveRequest(
                    $current->id->toString(),
                    $current->user->id,
                    $current->createdAt,
                    $current->leaveType->isAffectingBalance,
                )) {
                $this->userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
                    userId: $current->user->id,
                    delta: $current->workDays,
                    type: BalanceLedgerEntryTypeEnum::Restoration,
                    source: $source,
                    actorUserId: $actorUserId,
                    leaveRequestId: $current->id->toString(),
                ));
            }

            if ($ownsTransaction) {
                $this->leaveRequestRepository->commit();
            }
        } catch (\Throwable $throwable) {
            if ($ownsTransaction) {
                $this->leaveRequestRepository->rollback();
            }

            throw $throwable;
        }

        return true;
    }

    private function canTransition(LeaveRequestStatusEnum $current, LeaveRequestStatusEnum $target): bool
    {
        return match ($target) {
            LeaveRequestStatusEnum::Approved,
            LeaveRequestStatusEnum::Rejected,
            LeaveRequestStatusEnum::Cancelled => LeaveRequestStatusEnum::Pending === $current,
            LeaveRequestStatusEnum::Withdrawn => in_array($current, [LeaveRequestStatusEnum::Pending, LeaveRequestStatusEnum::Approved], true),
            default => false,
        };
    }

    private function restoresBalance(LeaveRequestStatusEnum $status): bool
    {
        return in_array($status, [
            LeaveRequestStatusEnum::Rejected,
            LeaveRequestStatusEnum::Cancelled,
            LeaveRequestStatusEnum::Withdrawn,
        ], true);
    }
}
