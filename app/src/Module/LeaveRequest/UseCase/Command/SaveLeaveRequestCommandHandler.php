<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Command;

use App\Module\LeaveRequest\Event\LeaveRequestSavedEvent;
use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\LeaveRequest\Command\SaveLeaveRequestCommand;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use Psr\EventDispatcher\EventDispatcherInterface;
use Ramsey\Uuid\Uuid;

class SaveLeaveRequestCommandHandler
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly LeaveRequestRepositoryInterface $leaveRequestRepository,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function handle(SaveLeaveRequestCommand $command, int $workDaysNumber): void
    {
        $userDTO = $command->userDTO;

        $leaveRequestDTO = new LeaveRequestDTO(
            id: Uuid::uuid4(),
            workDays: $workDaysNumber,
            leaveType: $command->leaveRequestTypeDTO,
            status: LeaveRequestStatusEnum::Pending,
            startDate: $command->startDate,
            endDate: $command->endDate,
            user: $userDTO,
        );

        $saved = false;

        try {
            $this->leaveRequestRepository->beginTransaction();

            $this->leaveRequestRepository->saveLeaveRequest($leaveRequestDTO);
            $saved = true;

            if ($command->leaveRequestTypeDTO->isAffectingBalance) {
                $this->userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
                    userId: $userDTO->id,
                    delta: -$workDaysNumber,
                    type: BalanceLedgerEntryTypeEnum::Usage,
                    source: BalanceLedgerSourceEnum::App,
                    actorUserId: $userDTO->id,
                    leaveRequestId: $leaveRequestDTO->id->toString(),
                ));
            }

            $this->leaveRequestRepository->flush();

            $this->leaveRequestRepository->commit();
        } catch (\Throwable $throwable) {
            $this->leaveRequestRepository->rollback();
            if ($saved) {
                $this->leaveRequestRepository->discardPending($leaveRequestDTO);
            }

            throw $throwable;
        }

        $this->dispatcher->dispatch(new LeaveRequestSavedEvent($leaveRequestDTO));
    }
}
