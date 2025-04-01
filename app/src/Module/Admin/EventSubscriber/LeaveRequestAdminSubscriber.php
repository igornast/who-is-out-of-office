<?php

declare(strict_types=1);

namespace App\Module\Admin\EventSubscriber;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\SlackFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LeaveRequestAdminSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly UserFacadeInterface $userFacade,
        private readonly SlackFacadeInterface $slackFacade,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'calculateWorkDays',
            AfterEntityPersistedEvent::class => 'updateUserCurrentLeaveBalance',
        ];
    }

    public function calculateWorkDays(BeforeEntityPersistedEvent $event): void
    {
        $leaveRequest = $event->getEntityInstance();

        if (!$leaveRequest instanceof LeaveRequest) {
            return;
        }

        $workDays = $this->leaveRequestFacade->calculateWorkDays(
            startDate: $leaveRequest->startDate,
            endDate: $leaveRequest->endDate
        );

        $leaveRequest->workDays = $workDays;
    }

    public function updateUserCurrentLeaveBalance(AfterEntityPersistedEvent $event): void
    {
        $leaveRequest = $event->getEntityInstance();

        if (!$leaveRequest instanceof LeaveRequest) {
            return;
        }

        $this->userFacade->updateUserCurrentLeaveBalance(
            $leaveRequest->user->id->toString(),
            -$leaveRequest->workDays,
        );

        $this->slackFacade->notifyOnNewLeaveRequest(LeaveRequestDTO::fromEntity($leaveRequest));
    }
}
