<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\EventListener;

use App\Module\LeaveRequest\Event\LeaveRequestSavedEvent;
use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(LeaveRequestSavedEvent::class)]
class LeaveRequestListener
{
    public function __construct(private readonly SlackFacadeInterface $slackFacade)
    {
    }

    public function __invoke(LeaveRequestSavedEvent $event): void
    {
        $this->slackFacade->notifyOnNewLeaveRequest($event->leaveRequestDTO);
    }
}
