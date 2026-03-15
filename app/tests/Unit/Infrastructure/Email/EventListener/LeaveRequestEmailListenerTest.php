<?php

declare(strict_types=1);

use App\Infrastructure\Email\EventListener\LeaveRequestEmailListener;
use App\Module\LeaveRequest\Event\LeaveRequestSavedEvent;
use App\Shared\Facade\EmailFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->emailFacade = mock(EmailFacadeInterface::class);

    $this->listener = new LeaveRequestEmailListener(
        emailFacade: $this->emailFacade,
    );
});

it('sends pending approval email when leave request is saved', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create();
    $event = new LeaveRequestSavedEvent($leaveRequestDTO);

    $this->emailFacade
        ->expects('sendLeaveRequestPendingApprovalEmail')
        ->once()
        ->with($leaveRequestDTO);

    $this->listener->__invoke($event);
});
