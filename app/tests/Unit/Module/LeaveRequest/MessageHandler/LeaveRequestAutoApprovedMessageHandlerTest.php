<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Message\LeaveRequestAutoApprovedMessage;
use App\Module\LeaveRequest\MessageHandler\LeaveRequestAutoApprovedMessageHandler;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\SlackFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->slackFacade = mock(SlackFacadeInterface::class);

    $this->handler = new LeaveRequestAutoApprovedMessageHandler(
        leaveRequestFacade: $this->leaveRequestFacade,
        slackFacade: $this->slackFacade
    );
});

it('sends Slack notification when leave request is found', function () {
    $leaveRequestId = '123e4567-e89b-12d3-a456-426614174000';
    $leaveRequestDTO = LeaveRequestDTOFixture::create();
    $message = new LeaveRequestAutoApprovedMessage($leaveRequestId);

    $this->leaveRequestFacade
        ->expects('getById')
        ->once()
        ->with($leaveRequestId)
        ->andReturn($leaveRequestDTO);

    $this->slackFacade
        ->expects('updateLeaveRequestNotificationAsAutoApproved')
        ->once()
        ->with($leaveRequestDTO);

    $this->slackFacade
        ->expects('notifyUserOnLeaveRequestChange')
        ->once()
        ->with($leaveRequestDTO);

    ($this->handler)($message);
});

it('does not send notification when leave request is not found', function () {
    $leaveRequestId = '123e4567-e89b-12d3-a456-426614174000';
    $message = new LeaveRequestAutoApprovedMessage($leaveRequestId);

    $this->leaveRequestFacade
        ->expects('getById')
        ->once()
        ->with($leaveRequestId)
        ->andReturn(null);

    $this->slackFacade
        ->expects('updateLeaveRequestNotificationAsAutoApproved')
        ->never();

    $this->slackFacade
        ->expects('notifyUserOnLeaveRequestChange')
        ->never();

    ($this->handler)($message);
});

it('passes correct leave request DTO to Slack facade', function () {
    $leaveRequestId = 'test-id-456';
    $leaveRequestDTO = LeaveRequestDTOFixture::create();
    $message = new LeaveRequestAutoApprovedMessage($leaveRequestId);

    $this->leaveRequestFacade
        ->expects('getById')
        ->once()
        ->with($leaveRequestId)
        ->andReturn($leaveRequestDTO);

    $this->slackFacade
        ->expects('updateLeaveRequestNotificationAsAutoApproved')
        ->once()
        ->withArgs(fn ($dto) => $dto === $leaveRequestDTO);

    $this->slackFacade
        ->expects('notifyUserOnLeaveRequestChange')
        ->once()
        ->withArgs(fn ($dto) => $dto === $leaveRequestDTO);

    ($this->handler)($message);
});
