<?php

declare(strict_types=1);

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Infrastructure\Slack\UseCase\Command\UpdateLeaveRequestWithInteractiveNotificationCommandHandler;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->userFacade = mock(UserFacadeInterface::class);

    $this->handler = new UpdateLeaveRequestWithInteractiveNotificationCommandHandler(
        leaveRequestFacade: $this->leaveRequestFacade,
        userFacade: $this->userFacade
    );
});

it('throws exception when leave request not found', function () {
    $notificationDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Approved,
        type: 'leave-request',
        identifier: '123e4567-e89b-12d3-a456-426614174000',
        channel: 'C1234567890',
        responseUrl: 'https://hooks.slack.com/test',
        memberId: 'U123456'
    );

    $this->leaveRequestFacade
        ->expects('getById')
        ->once()
        ->with('123e4567-e89b-12d3-a456-426614174000')
        ->andReturn(null);

    expect(fn () => $this->handler->handle($notificationDTO))
        ->toThrow(RuntimeException::class, 'Leave request with id "123e4567-e89b-12d3-a456-426614174000" not found');
});

it('updates leave request with approved status and approver', function () {
    $approver = UserDTOFixture::create([
        'id' => 'approver-123',
        'slackMemberId' => 'U123456',
    ]);

    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('123e4567-e89b-12d3-a456-426614174000'),
        'status' => LeaveRequestStatusEnum::Pending,
        'approvedBy' => null,
        'workDays' => 5,
    ]);

    $notificationDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Approved,
        type: 'leave-request',
        identifier: $leaveRequestDTO->id->toString(),
        channel: 'C1234567890',
        responseUrl: 'https://hooks.slack.com/test',
        memberId: 'U123456'
    );

    $this->leaveRequestFacade
        ->expects('getById')
        ->once()
        ->with($leaveRequestDTO->id->toString())
        ->andReturn($leaveRequestDTO);

    $this->userFacade
        ->expects('getUserBySlackMemberId')
        ->once()
        ->with('U123456')
        ->andReturn($approver);

    $this->leaveRequestFacade
        ->expects('update')
        ->once()
        ->with($leaveRequestDTO);

    $result = $this->handler->handle($notificationDTO);

    expect($result->status)
        ->toBe(LeaveRequestStatusEnum::Approved)
        ->and($result->approvedBy)->toBe($approver);
});

it('removes leave request and returns blocked days when rejected', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('987e6543-e21b-12d3-a456-426614174999'),
        'status' => LeaveRequestStatusEnum::Pending,
        'approvedBy' => null,
        'workDays' => 10,
    ]);

    $notificationDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Rejected,
        type: 'leave-request',
        identifier: $leaveRequestDTO->id->toString(),
        channel: 'C1234567890',
        responseUrl: 'https://hooks.slack.com/test',
        memberId: 'U123456'
    );

    $approver = UserDTOFixture::create([
        'slackMemberId' => 'U123456',
    ]);

    $this->leaveRequestFacade
        ->expects('getById')
        ->once()
        ->with($leaveRequestDTO->id->toString())
        ->andReturn($leaveRequestDTO);

    $this->userFacade
        ->expects('getUserBySlackMemberId')
        ->once()
        ->with('U123456')
        ->andReturn($approver);

    $this->userFacade
        ->expects('updateUserCurrentLeaveBalance')
        ->once()
        ->with($leaveRequestDTO->user->id, 10);

    $this->leaveRequestFacade
        ->expects('remove')
        ->once()
        ->with($leaveRequestDTO);

    $this->leaveRequestFacade
        ->expects('update')
        ->never();

    $result = $this->handler->handle($notificationDTO);

    expect($result->status)
        ->toBe(LeaveRequestStatusEnum::Rejected);
});

it('removes leave request and returns blocked days when cancelled', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('111e2222-e33b-44d5-a666-777777777777'),
        'status' => LeaveRequestStatusEnum::Pending,
        'approvedBy' => null,
        'workDays' => 7,
    ]);

    $notificationDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Cancelled,
        type: 'leave-request',
        identifier: $leaveRequestDTO->id->toString(),
        channel: 'C1234567890',
        responseUrl: 'https://hooks.slack.com/test',
        memberId: null
    );

    $this->leaveRequestFacade
        ->expects('getById')
        ->once()
        ->with($leaveRequestDTO->id->toString())
        ->andReturn($leaveRequestDTO);

    $this->userFacade
        ->expects('getUserBySlackMemberId')
        ->never();

    $this->userFacade
        ->expects('updateUserCurrentLeaveBalance')
        ->once()
        ->with($leaveRequestDTO->user->id, 7);

    $this->leaveRequestFacade
        ->expects('remove')
        ->once()
        ->with($leaveRequestDTO);

    $this->leaveRequestFacade
        ->expects('update')
        ->never();

    $result = $this->handler->handle($notificationDTO);

    expect($result->status)
        ->toBe(LeaveRequestStatusEnum::Cancelled)
        ->and($result->approvedBy)->toBeNull();
});

it('returns leave request unchanged when already withdrawn', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('222e3333-e44b-55d6-a777-888888888888'),
        'status' => LeaveRequestStatusEnum::Withdrawn,
        'approvedBy' => null,
    ]);

    $notificationDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Approved,
        type: 'leave-request',
        identifier: $leaveRequestDTO->id->toString(),
        channel: 'C1234567890',
        responseUrl: 'https://hooks.slack.com/test',
        memberId: 'U123456'
    );

    $this->leaveRequestFacade
        ->expects('getById')
        ->once()
        ->with($leaveRequestDTO->id->toString())
        ->andReturn($leaveRequestDTO);

    $this->userFacade
        ->expects('getUserBySlackMemberId')
        ->never();

    $this->leaveRequestFacade
        ->expects('update')
        ->never();

    $this->leaveRequestFacade
        ->expects('remove')
        ->never();

    $result = $this->handler->handle($notificationDTO);

    expect($result->status)
        ->toBe(LeaveRequestStatusEnum::Withdrawn);
});

it('returns leave request unchanged when already rejected', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('333e4444-e55b-66d7-a888-999999999999'),
        'status' => LeaveRequestStatusEnum::Rejected,
        'approvedBy' => null,
    ]);

    $notificationDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Approved,
        type: 'leave-request',
        identifier: $leaveRequestDTO->id->toString(),
        channel: 'C1234567890',
        responseUrl: 'https://hooks.slack.com/test',
        memberId: 'U123456'
    );

    $this->leaveRequestFacade
        ->expects('getById')
        ->once()
        ->with($leaveRequestDTO->id->toString())
        ->andReturn($leaveRequestDTO);

    $this->userFacade
        ->expects('getUserBySlackMemberId')
        ->never();

    $this->leaveRequestFacade
        ->expects('update')
        ->never();

    $this->leaveRequestFacade
        ->expects('remove')
        ->never();

    $result = $this->handler->handle($notificationDTO);

    expect($result->status)
        ->toBe(LeaveRequestStatusEnum::Rejected);
});

it('updates leave request without approver when member id is null', function () {
    $leaveRequestDTO = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('444e5555-e66b-77d8-a999-000000000000'),
        'status' => LeaveRequestStatusEnum::Pending,
        'approvedBy' => null,
    ]);

    $notificationDTO = new InteractiveNotificationDTO(
        status: LeaveRequestStatusEnum::Approved,
        type: 'leave-request',
        identifier: $leaveRequestDTO->id->toString(),
        channel: 'C1234567890',
        responseUrl: 'https://hooks.slack.com/test',
        memberId: null
    );

    $this->leaveRequestFacade
        ->expects('getById')
        ->once()
        ->with($leaveRequestDTO->id->toString())
        ->andReturn($leaveRequestDTO);

    $this->userFacade
        ->expects('getUserBySlackMemberId')
        ->never();

    $this->leaveRequestFacade
        ->expects('update')
        ->once()
        ->with($leaveRequestDTO);

    $result = $this->handler->handle($notificationDTO);

    expect($result->status)
        ->toBe(LeaveRequestStatusEnum::Approved)
        ->and($result->approvedBy)->toBeNull();
});
