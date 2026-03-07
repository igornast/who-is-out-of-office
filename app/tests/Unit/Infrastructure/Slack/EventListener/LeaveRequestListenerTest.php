<?php

declare(strict_types=1);

use App\Infrastructure\Slack\EventListener\LeaveRequestListener;
use App\Module\LeaveRequest\Event\LeaveRequestSavedEvent;
use App\Shared\Facade\SlackFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->slackFacade = mock(SlackFacadeInterface::class);

    $this->listener = new LeaveRequestListener(slackFacade: $this->slackFacade);
});

it('notifies slack on new leave request', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create();
    $event = new LeaveRequestSavedEvent($leaveRequestDTO);

    $this->slackFacade
        ->expects('notifyOnNewLeaveRequest')
        ->once()
        ->with($leaveRequestDTO);

    ($this->listener)($event);
});
