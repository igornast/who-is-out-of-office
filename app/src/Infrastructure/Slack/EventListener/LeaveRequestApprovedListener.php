<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\EventListener;

use App\Module\LeaveRequest\Event\LeaveRequestApprovedEvent;
use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(LeaveRequestApprovedEvent::class)]
class LeaveRequestApprovedListener
{
    public function __construct(private readonly SlackFacadeInterface $slackFacade)
    {
    }

    public function __invoke(LeaveRequestApprovedEvent $event): void
    {
        $this->slackFacade->notifyOnNewLeaveRequest($event->leaveRequestDTO);
    }
}
