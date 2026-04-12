<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Command\MarkExternalStatusSyncedCommandHandler;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);
    $this->handler = new MarkExternalStatusSyncedCommandHandler(repository: $this->repository);
});

it('marks leave request as synced', function (): void {
    $this->repository
        ->expects('markExternalStatusSynced')
        ->once()
        ->with('lr-123', true);

    $this->handler->handle('lr-123', true);
});

it('marks leave request as unsynced', function (): void {
    $this->repository
        ->expects('markExternalStatusSynced')
        ->once()
        ->with('lr-456', false);

    $this->handler->handle('lr-456', false);
});
