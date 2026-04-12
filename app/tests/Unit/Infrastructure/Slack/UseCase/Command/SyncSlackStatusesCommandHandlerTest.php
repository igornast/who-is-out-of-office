<?php

declare(strict_types=1);

use App\Infrastructure\Slack\UseCase\Command\ClearSlackStatusCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\SetSlackStatusCommandHandler;
use App\Infrastructure\Slack\UseCase\Command\SyncSlackStatusesCommandHandler;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->leaveRequestFacade = mock(LeaveRequestFacadeInterface::class);
    $this->appSettingsFacade = mock(AppSettingsFacadeInterface::class);
    $this->setHandler = mock(SetSlackStatusCommandHandler::class);
    $this->clearHandler = mock(ClearSlackStatusCommandHandler::class);
    $this->logger = mock(LoggerInterface::class);

    $this->appSettingsFacade->allows('isSlackStatusSyncEnabled')->andReturn(true)->byDefault();

    $this->handler = new SyncSlackStatusesCommandHandler(
        $this->leaveRequestFacade,
        $this->appSettingsFacade,
        $this->setHandler,
        $this->clearHandler,
        $this->logger,
    );
});

it('does nothing when slack status sync is disabled in settings', function (): void {
    $this->appSettingsFacade->expects('isSlackStatusSyncEnabled')->andReturn(false);

    $this->leaveRequestFacade->shouldNotReceive('findApprovedActiveNotSynced');
    $this->leaveRequestFacade->shouldNotReceive('findSyncedNeedingClear');
    $this->setHandler->shouldNotReceive('handle');
    $this->clearHandler->shouldNotReceive('handle');

    $this->handler->handle();
});

it('sets status and marks synced for active not-synced leaves', function (): void {
    $lr1 = LeaveRequestDTOFixture::create();
    $lr2 = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->once()
        ->andReturn([$lr1, $lr2]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->once()
        ->andReturn([]);

    $this->setHandler
        ->expects('handle')
        ->twice()
        ->andReturn(true);

    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->twice();

    $this->handler->handle();
});

it('clears status and marks unsynced for ended or rejected leaves', function (): void {
    $lr1 = LeaveRequestDTOFixture::create();
    $lr2 = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->once()
        ->andReturn([]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->once()
        ->andReturn([$lr1, $lr2]);

    $this->clearHandler
        ->expects('handle')
        ->twice()
        ->andReturn(true);

    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->twice();

    $this->setHandler->shouldNotReceive('handle');

    $this->handler->handle();
});

it('does not mark synced when set handler returns false', function (): void {
    $lr = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->andReturn([$lr]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->andReturn([]);

    $this->setHandler
        ->expects('handle')
        ->once()
        ->andReturn(false);

    $this->leaveRequestFacade->shouldNotReceive('markExternalStatusSynced');

    $this->handler->handle();
});

it('does not mark unsynced when clear handler returns false', function (): void {
    $lr = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->andReturn([]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->andReturn([$lr]);

    $this->clearHandler
        ->expects('handle')
        ->once()
        ->andReturn(false);

    $this->leaveRequestFacade->shouldNotReceive('markExternalStatusSynced');

    $this->handler->handle();
});

it('continues batch when one set throws', function (): void {
    $lr1 = LeaveRequestDTOFixture::create();
    $lr2 = LeaveRequestDTOFixture::create();
    $lr3 = LeaveRequestDTOFixture::create();

    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->andReturn([$lr1, $lr2, $lr3]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->andReturn([]);

    $this->setHandler
        ->expects('handle')
        ->times(3)
        ->andReturnUsing(function () {
            static $call = 0;
            ++$call;
            if (2 === $call) {
                throw new RuntimeException('boom');
            }

            return true;
        });

    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->twice();

    $this->logger->expects('error')->once();

    $this->handler->handle();
});

it('handles both sets and clears in one pass', function (): void {
    $this->leaveRequestFacade
        ->expects('findApprovedActiveNotSynced')
        ->andReturn([LeaveRequestDTOFixture::create()]);
    $this->leaveRequestFacade
        ->expects('findSyncedNeedingClear')
        ->andReturn([LeaveRequestDTOFixture::create(), LeaveRequestDTOFixture::create()]);

    $this->setHandler->expects('handle')->once()->andReturn(true);
    $this->clearHandler->expects('handle')->twice()->andReturn(true);

    $this->leaveRequestFacade
        ->expects('markExternalStatusSynced')
        ->times(3);

    $this->handler->handle();
});
