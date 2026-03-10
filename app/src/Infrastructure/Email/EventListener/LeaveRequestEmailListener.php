<?php

declare(strict_types=1);

namespace App\Infrastructure\Email\EventListener;

use App\Module\LeaveRequest\Event\LeaveRequestSavedEvent;
use App\Shared\Facade\EmailFacadeInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(LeaveRequestSavedEvent::class)]
class LeaveRequestEmailListener
{
    public function __construct(
        private readonly EmailFacadeInterface $emailFacade,
    ) {
    }

    public function __invoke(LeaveRequestSavedEvent $event): void
    {
        $this->emailFacade->sendLeaveRequestPendingApprovalEmail($event->leaveRequestDTO);
    }
}
