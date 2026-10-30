<?php

declare(strict_types=1);

use App\Module\User\Repository\BalanceLedgerEntryRepositoryInterface;
use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Query\GetBalanceStatementQueryHandler;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Tests\_fixtures\Shared\DTO\LeavePeriodDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->leavePeriodRepository = mock(LeavePeriodRepositoryInterface::class);
    $this->ledgerRepository = mock(BalanceLedgerEntryRepositoryInterface::class);

    $this->handler = new GetBalanceStatementQueryHandler(
        userRepository: $this->userRepository,
        leavePeriodRepository: $this->leavePeriodRepository,
        balanceLedgerEntryRepository: $this->ledgerRepository,
    );
});

it('reports totals per type and the live balance for an open period', function () {
    $user = UserDTOFixture::create(['currentLeaveBalance' => 19]);
    $period = LeavePeriodDTOFixture::create([
        'userId' => $user->id,
        'openingBalance' => 3,
        'closingBalance' => null,
        'closedAt' => null,
    ]);

    $this->leavePeriodRepository->expects('findOneById')->once()->with($period->id)->andReturn($period);
    $this->userRepository->expects('findOneById')->once()->with($user->id)->andReturn($user);
    $this->ledgerRepository
        ->expects('sumDeltasByTypeForPeriod')
        ->once()
        ->with($period->id)
        ->andReturn([
            BalanceLedgerEntryTypeEnum::Accrual->value => 24,
            BalanceLedgerEntryTypeEnum::Usage->value => -10,
            BalanceLedgerEntryTypeEnum::Restoration->value => 2,
        ]);

    $statement = $this->handler->handle($user->id, $period->id);

    expect($statement->openingBalance)->toBe(3)
        ->and($statement->granted)->toBe(24)
        ->and($statement->used)->toBe(-10)
        ->and($statement->restored)->toBe(2)
        ->and($statement->adjusted)->toBe(0)
        ->and($statement->currentBalance)->toBe(19);
});

it('reports the closing balance for a closed period', function () {
    $user = UserDTOFixture::create(['currentLeaveBalance' => 19]);
    $period = LeavePeriodDTOFixture::create([
        'userId' => $user->id,
        'openingBalance' => 0,
        'closingBalance' => 6,
        'closedAt' => new DateTimeImmutable('-1 month'),
    ]);

    $this->leavePeriodRepository->expects('findOneById')->once()->andReturn($period);
    $this->userRepository->expects('findOneById')->once()->andReturn($user);
    $this->ledgerRepository->expects('sumDeltasByTypeForPeriod')->once()->andReturn([]);

    $statement = $this->handler->handle($user->id, $period->id);

    expect($statement->currentBalance)->toBe(6)
        ->and($statement->granted)->toBe(0);
});

it('throws when the period does not exist', function () {
    $this->leavePeriodRepository->expects('findOneById')->once()->andReturn(null);

    $this->handler->handle('user-1', 'missing');
})->throws(DomainException::class);

it('throws when the period belongs to another user', function () {
    $period = LeavePeriodDTOFixture::create(['userId' => 'user-2']);
    $this->leavePeriodRepository->expects('findOneById')->once()->andReturn($period);
    $this->userRepository->expects('findOneById')->never();
    $this->ledgerRepository->expects('sumDeltasByTypeForPeriod')->never();

    $this->handler->handle('user-1', $period->id);
})->throws(DomainException::class);

it('throws when the user for an open period does not exist', function () {
    $period = LeavePeriodDTOFixture::create([
        'userId' => 'missing-user',
        'closingBalance' => null,
        'closedAt' => null,
    ]);
    $this->leavePeriodRepository->expects('findOneById')->once()->andReturn($period);
    $this->userRepository->expects('findOneById')->once()->andReturn(null);
    $this->ledgerRepository->expects('sumDeltasByTypeForPeriod')->never();

    $this->handler->handle('missing-user', $period->id);
})->throws(DomainException::class);
