<?php

declare(strict_types=1);

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Infrastructure\Slack\SlackFacade;
use App\Infrastructure\Slack\UseCase\Command\NotifyNewLeaveRequestCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\NotifyUserLeaveRequestStatusChangeCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\SendChangeConfirmationToAbsenceChannelCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\UpdateAutoApprovedSlackNotificationCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\UpdateLeaveRequestWithInteractiveNotificationCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\WeeklyDigestNotificationCommandHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->notifyNewLeaveRequestHandler = mock(NotifyNewLeaveRequestCommandHandler::class);
    $this->updateLeaveRequestWithNotificationHandler = mock(UpdateLeaveRequestWithInteractiveNotificationCommandHandler::class);
    $this->sendConfirmationToChannelHandler = mock(SendChangeConfirmationToAbsenceChannelCommandHandler::class);
    $this->notifyUserLeaveRequestStatusChanged = mock(NotifyUserLeaveRequestStatusChangeCommandHandler::class);
    $this->weeklyNotificationHandler = mock(WeeklyDigestNotificationCommandHandler::class);
    $this->updateAutoApprovedSlackNotificationHandler = mock(UpdateAutoApprovedSlackNotificationCommandHandler::class);

    $this->facade = new SlackFacade(
        notifyNewLeaveRequestHandler: $this->notifyNewLeaveRequestHandler,
        updateLeaveRequestWithNotificationHandler: $this->updateLeaveRequestWithNotificationHandler,
        sendConfirmationToChannelHandler: $this->sendConfirmationToChannelHandler,
        notifyUserLeaveRequestStatusChanged: $this->notifyUserLeaveRequestStatusChanged,
        weeklyNotificationHandler: $this->weeklyNotificationHandler,
        updateAutoApprovedSlackNotificationHandler: $this->updateAutoApprovedSlackNotificationHandler,
    );
});

it('delegates notifyOnNewLeaveRequest to handler', function () {
    $dto = LeaveRequestDTOFixture::create();

    $this->notifyNewLeaveRequestHandler
        ->expects('handle')
        ->once()
        ->with($dto);

    $this->facade->notifyOnNewLeaveRequest($dto);
});

it('handles interactive notification and notifies user when not auto approved', function () {
    $interactiveDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Approved,
        type: 'leave-request',
        identifier: 'lr-1',
        channel: 'C123',
        responseUrl: 'https://hooks.slack.com/test',
        memberId: 'U123',
    );
    $leaveRequestDTO = LeaveRequestDTOFixture::create(['isAutoApproved' => false]);

    $this->updateLeaveRequestWithNotificationHandler
        ->expects('handle')
        ->once()
        ->with($interactiveDTO)
        ->andReturn($leaveRequestDTO);

    $this->sendConfirmationToChannelHandler
        ->expects('handle')
        ->once()
        ->with($leaveRequestDTO, $interactiveDTO);

    $this->notifyUserLeaveRequestStatusChanged
        ->expects('handle')
        ->once()
        ->with($leaveRequestDTO);

    $result = $this->facade->handleInteractiveNotification($interactiveDTO);

    expect($result)->toBe($leaveRequestDTO);
});

it('handles interactive notification and skips user notification when auto approved', function () {
    $interactiveDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Approved,
        type: 'leave-request',
        identifier: 'lr-1',
        channel: 'C123',
        responseUrl: 'https://hooks.slack.com/test',
        memberId: 'U123',
    );
    $leaveRequestDTO = LeaveRequestDTOFixture::create(['isAutoApproved' => true]);

    $this->updateLeaveRequestWithNotificationHandler
        ->expects('handle')
        ->once()
        ->with($interactiveDTO)
        ->andReturn($leaveRequestDTO);

    $this->sendConfirmationToChannelHandler
        ->expects('handle')
        ->once()
        ->with($leaveRequestDTO, $interactiveDTO);

    $this->notifyUserLeaveRequestStatusChanged
        ->shouldNotReceive('handle');

    $result = $this->facade->handleInteractiveNotification($interactiveDTO);

    expect($result)->toBe($leaveRequestDTO);
});

it('delegates notifyUserOnLeaveRequestChange to handler', function () {
    $dto = LeaveRequestDTOFixture::create();

    $this->notifyUserLeaveRequestStatusChanged
        ->expects('handle')
        ->once()
        ->with($dto);

    $this->facade->notifyUserOnLeaveRequestChange($dto);
});

it('delegates sendWeeklyDigestNotification to handler', function () {
    $this->weeklyNotificationHandler
        ->expects('handle')
        ->once();

    $this->facade->sendWeeklyDigestNotification();
});

it('delegates updateLeaveRequestNotificationAsAutoApproved to handler', function () {
    $dto = LeaveRequestDTOFixture::create();

    $this->updateAutoApprovedSlackNotificationHandler
        ->expects('handle')
        ->once()
        ->with($dto);

    $this->facade->updateLeaveRequestNotificationAsAutoApproved($dto);
});
