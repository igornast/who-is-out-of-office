<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;

class UpdateLeaveRequestWithInteractiveNotificationCommandHandler
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    public function handle(InteractiveNotificationDTO $notificationDTO): LeaveRequestDTO
    {
        $leaveRequestDTO = $this->leaveRequestFacade->getById($notificationDTO->identifier);
        $approvedByUserDTO = $this->userFacade->getUserBySlackMemberId($notificationDTO->memberId);

        $leaveRequestDTO->status = $notificationDTO->status;
        $leaveRequestDTO->approvedBy = $approvedByUserDTO;

        $this->leaveRequestFacade->update($leaveRequestDTO);

        return $leaveRequestDTO;
    }
}
