<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\FindSyncedNeedingClearQueryHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);
    $this->handler = new FindSyncedNeedingClearQueryHandler(repository: $this->repository);
});

it('delegates to repository', function (): void {
    $items = [LeaveRequestDTOFixture::create()];

    $this->repository
        ->expects('findSyncedNeedingClear')
        ->once()
        ->andReturn($items);

    expect($this->handler->handle())->toBe($items);
});

it('returns empty array when nothing needs clearing', function (): void {
    $this->repository
        ->expects('findSyncedNeedingClear')
        ->once()
        ->andReturn([]);

    expect($this->handler->handle())->toBe([]);
});
