<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Command;

use App\Module\LeaveRequest\Event\LeaveRequestSavedEvent;
use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequest\Command\SaveLeaveRequestCommand;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\LeaveRequestTypeEnum;
use App\Shared\Facade\UserFacadeInterface;
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
            id: Uuid::uuid4()->toString(),
            workDays: $workDaysNumber,
            status: LeaveRequestStatusEnum::Pending,
            leaveType: $command->leaveRequestType,
            startDate: $command->startDate,
            endDate: $command->endDate,
            user: $userDTO,
        );


        try {
            $this->leaveRequestRepository->beginTransaction();

            $this->leaveRequestRepository->saveLeaveRequest($leaveRequestDTO);
            if (LeaveRequestTypeEnum::Vacation === $command->leaveRequestType) {
                $this->userFacade->updateUserCurrentLeaveBalance($userDTO->id, -$workDaysNumber);
            }

            $this->leaveRequestRepository->commit();
        } catch (\Exception $e) {
            $this->leaveRequestRepository->rollback();

            throw $e;
        }

        $this->dispatcher->dispatch(new LeaveRequestSavedEvent($leaveRequestDTO));
    }
}
