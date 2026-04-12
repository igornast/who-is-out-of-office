<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\FindApprovedActiveNotSyncedQueryHandler;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);
    $this->handler = new FindApprovedActiveNotSyncedQueryHandler(repository: $this->repository);
});

it('delegates to repository', function (): void {
    $items = [LeaveRequestDTOFixture::create(), LeaveRequestDTOFixture::create()];

    $this->repository
        ->expects('findApprovedActiveNotSynced')
        ->once()
        ->andReturn($items);

    expect($this->handler->handle())->toBe($items);
});

it('returns empty array when no unsynced leaves', function (): void {
    $this->repository
        ->expects('findApprovedActiveNotSynced')
        ->once()
        ->andReturn([]);

    expect($this->handler->handle())->toBe([]);
});
