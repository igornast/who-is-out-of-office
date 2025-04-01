<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Shared\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\Facade\LeaveRequestFacadeInterface;

class InteractiveNotificationRequestHandler
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    public function handle(InteractiveNotificationDTO $notificationDTO): void
    {
        $leaveRequestDTO = $this->leaveRequestFacade->getById($notificationDTO->identifier);

        $leaveRequestDTO->status = $notificationDTO->status;

        $this->leaveRequestFacade->update($leaveRequestDTO);
    }
}
