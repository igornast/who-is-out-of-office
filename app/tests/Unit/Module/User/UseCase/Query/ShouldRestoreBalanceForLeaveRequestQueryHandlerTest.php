<?php

declare(strict_types=1);

use App\Module\User\Repository\BalanceLedgerEntryRepositoryInterface;
use App\Module\User\UseCase\Query\ShouldRestoreBalanceForLeaveRequestQueryHandler;

beforeEach(function (): void {
    $this->repository = mock(BalanceLedgerEntryRepositoryInterface::class);
    $this->handler = new ShouldRestoreBalanceForLeaveRequestQueryHandler($this->repository);
});

it('restores when a usage entry exists', function (): void {
    $this->repository->expects('hasUsageForLeaveRequest')->once()->with('request-id')->andReturnTrue();
    $this->repository->expects('hasEntryForLeaveRequest')->never();
    $this->repository->expects('hasMigrationOpeningEntryAfter')->never();

    expect($this->handler->handle('request-id', 'user-id', new DateTimeImmutable('-2 days'), false))->toBeTrue();
});

it('does not restore when another ledger entry references the request', function (): void {
    $this->repository->expects('hasUsageForLeaveRequest')->once()->with('request-id')->andReturnFalse();
    $this->repository->expects('hasEntryForLeaveRequest')->once()->with('request-id')->andReturnTrue();
    $this->repository->expects('hasMigrationOpeningEntryAfter')->never();

    expect($this->handler->handle('request-id', 'user-id', new DateTimeImmutable('-2 days'), true))->toBeFalse();
});

it('does not restore when the current leave type does not affect balance', function (): void {
    $this->repository->expects('hasUsageForLeaveRequest')->once()->with('request-id')->andReturnFalse();
    $this->repository->expects('hasEntryForLeaveRequest')->never();
    $this->repository->expects('hasMigrationOpeningEntryAfter')->never();

    expect($this->handler->handle('request-id', 'user-id', new DateTimeImmutable('-2 days'), false))->toBeFalse();
});

it('restores a pre-migration balance-affecting request', function (): void {
    $createdAt = new DateTimeImmutable('-2 days');
    $this->repository->expects('hasUsageForLeaveRequest')->once()->with('request-id')->andReturnFalse();
    $this->repository->expects('hasEntryForLeaveRequest')->once()->with('request-id')->andReturnFalse();
    $this->repository->expects('hasMigrationOpeningEntryAfter')->once()->with('user-id', $createdAt)->andReturnTrue();

    expect($this->handler->handle('request-id', 'user-id', $createdAt, true))->toBeTrue();
});

it('does not restore a request created after migration', function (): void {
    $createdAt = new DateTimeImmutable('+2 days');
    $this->repository->expects('hasUsageForLeaveRequest')->once()->with('request-id')->andReturnFalse();
    $this->repository->expects('hasEntryForLeaveRequest')->once()->with('request-id')->andReturnFalse();
    $this->repository->expects('hasMigrationOpeningEntryAfter')->once()->with('user-id', $createdAt)->andReturnFalse();

    expect($this->handler->handle('request-id', 'user-id', $createdAt, true))->toBeFalse();
});

it('does not restore when the request creation time is unavailable', function (): void {
    $this->repository->expects('hasUsageForLeaveRequest')->once()->with('request-id')->andReturnFalse();
    $this->repository->expects('hasEntryForLeaveRequest')->once()->with('request-id')->andReturnFalse();
    $this->repository->expects('hasMigrationOpeningEntryAfter')->never();

    expect($this->handler->handle('request-id', 'user-id', null, true))->toBeFalse();
});
