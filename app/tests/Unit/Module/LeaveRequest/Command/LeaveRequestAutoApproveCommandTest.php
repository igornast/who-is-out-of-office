<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Command\LeaveRequestAutoApproveCommand;
use App\Module\LeaveRequest\Message\LeaveRequestAutoApprovedMessage;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

beforeEach(function (): void {
    $this->logger = mock(LoggerInterface::class);
    $this->appSettingsFacade = mock(AppSettingsFacadeInterface::class);
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->messageBus = mock(MessageBusInterface::class);

    $this->command = new LeaveRequestAutoApproveCommand(
        logger: $this->logger,
        appSettingsFacade: $this->appSettingsFacade,
        leaveRequestFacade: $this->leaveRequestFacade,
        messageBus: $this->messageBus
    );
});

it('returns success without processing when auto approve is disabled', function () {
    $this->appSettingsFacade
        ->expects('isAutoApprove')
        ->once()
        ->andReturn(false);

    $this->logger
        ->expects('debug')
        ->never();

    $this->leaveRequestFacade
        ->expects('getPendingLeaveRequests')
        ->never();

    $result = ($this->command)();

    expect($result)->toBe(Command::SUCCESS);
});

it('returns success when auto approve is enabled but no pending requests', function () {
    $autoApproveDelay = 5;
    $expectedCreatedBefore = new DateTimeImmutable()->modify(sprintf('-%d seconds', $autoApproveDelay * 60));

    $this->appSettingsFacade
        ->expects('isAutoApprove')
        ->once()
        ->andReturn(true);

    $this->appSettingsFacade
        ->expects('autoApproveDelay')
        ->once()
        ->andReturn($autoApproveDelay);

    $this->logger
        ->expects('debug')
        ->with('[LEAVE-REQUEST][AUTO]: Leave request auto approve run.')
        ->once();

    $this->leaveRequestFacade
        ->expects('getPendingLeaveRequests')
        ->once()
        ->withArgs(fn (DateTimeImmutable $createdBefore) => abs($createdBefore->getTimestamp() - $expectedCreatedBefore->getTimestamp()) < 2)
        ->andReturn([]);

    $this->logger
        ->expects('debug')
        ->with('[LEAVE-REQUEST][AUTO]: Auto approve done. Approved 0 requests.')
        ->once();

    $this->leaveRequestFacade
        ->expects('update')
        ->never();

    $this->messageBus
        ->expects('dispatch')
        ->never();

    $result = ($this->command)();

    expect($result)->toBe(Command::SUCCESS);
});

it('approves pending leave requests and dispatches messages', function () {
    $autoApproveDelay = 5;

    $leaveRequest1 = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('111e2222-e33b-44d5-a666-777777777777'),
        'status' => LeaveRequestStatusEnum::Pending,
        'isAutoApproved' => false,
    ]);

    $leaveRequest2 = LeaveRequestDTOFixture::create([
        'id' => Uuid::fromString('222e3333-e44b-55d6-a777-888888888888'),
        'status' => LeaveRequestStatusEnum::Pending,
        'isAutoApproved' => false,
    ]);

    $pendingRequests = [$leaveRequest1, $leaveRequest2];

    $this->appSettingsFacade
        ->expects('isAutoApprove')
        ->once()
        ->andReturn(true);

    $this->appSettingsFacade
        ->expects('autoApproveDelay')
        ->once()
        ->andReturn($autoApproveDelay);

    $this->logger
        ->expects('debug')
        ->with('[LEAVE-REQUEST][AUTO]: Leave request auto approve run.')
        ->once();

    $this->leaveRequestFacade
        ->expects('getPendingLeaveRequests')
        ->once()
        ->andReturn($pendingRequests);

    $this->leaveRequestFacade
        ->expects('update')
        ->twice()
        ->withArgs(fn ($leaveRequestDTO) => LeaveRequestStatusEnum::Approved === $leaveRequestDTO->status
                && true === $leaveRequestDTO->isAutoApproved);

    $this->messageBus
        ->expects('dispatch')
        ->twice()->andReturn(new Envelope(new LeaveRequestAutoApprovedMessage('test-id')));

    $this->logger
        ->expects('debug')
        ->with('[LEAVE-REQUEST][AUTO]: Auto approve done. Approved 2 requests.')
        ->once();

    $result = ($this->command)();

    expect($result)->toBe(Command::SUCCESS);
});

it('approves single pending leave request with correct message id', function () {
    $autoApproveDelay = 10;
    $leaveRequestId = Uuid::fromString('333e4444-e55b-66d7-a888-999999999999');

    $leaveRequest = LeaveRequestDTOFixture::create([
        'id' => $leaveRequestId,
        'status' => LeaveRequestStatusEnum::Pending,
        'isAutoApproved' => false,
    ]);

    $this->appSettingsFacade
        ->expects('isAutoApprove')
        ->once()
        ->andReturn(true);

    $this->appSettingsFacade
        ->expects('autoApproveDelay')
        ->once()
        ->andReturn($autoApproveDelay);

    $this->logger
        ->expects('debug')
        ->twice();

    $this->leaveRequestFacade
        ->expects('getPendingLeaveRequests')
        ->once()
        ->andReturn([$leaveRequest]);

    $this->leaveRequestFacade
        ->expects('update')
        ->once()
        ->with($leaveRequest);

    $this->messageBus
        ->expects('dispatch')
        ->once()
        ->andReturn(new Envelope(new LeaveRequestAutoApprovedMessage($leaveRequestId->toString())));

    $result = ($this->command)();

    expect($result)->toBe(Command::SUCCESS)
        ->and($leaveRequest->status)->toBe(LeaveRequestStatusEnum::Approved)
        ->and($leaveRequest->isAutoApproved)->toBeTrue();
});
