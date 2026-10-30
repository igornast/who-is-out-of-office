<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Doctrine\Repository\BalanceLedgerEntryRepository;
use App\Infrastructure\Doctrine\Repository\LeavePeriodRepository;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Shared\DTO\Balance\BalanceLedgerEntryDTO;
use App\Shared\DTO\Balance\LeavePeriodDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    self::bootKernel();
    $this->em = self::getContainer()->get(EntityManagerInterface::class);
    $this->periodRepository = self::getContainer()->get(LeavePeriodRepository::class);
    $this->ledgerRepository = self::getContainer()->get(BalanceLedgerEntryRepository::class);
    $this->user = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
});

afterEach(function (): void {
    self::ensureKernelShutdown();
});

it('stores a period and returns it as the open period', function () {
    $userId = $this->user->id->toString();
    $start = new DateTimeImmutable('-3 months');

    $this->periodRepository->add(new LeavePeriodDTO(
        id: Uuid::uuid4()->toString(),
        userId: $userId,
        allowanceGranted: 24,
        openingBalance: 3,
        startDate: $start,
        endDate: $start->modify('+1 year'),
    ));

    $open = $this->periodRepository->findOpenForUser($userId);

    expect($open)->not->toBeNull()
        ->and($open->allowanceGranted)->toBe(24)
        ->and($open->openingBalance)->toBe(3)
        ->and($open->isOpen())->toBeTrue();
});

it('stores ledger entries and sums them by type', function () {
    $userId = $this->user->id->toString();
    $start = new DateTimeImmutable('-2 months');
    $periodId = Uuid::uuid4()->toString();

    $this->periodRepository->add(new LeavePeriodDTO(
        id: $periodId,
        userId: $userId,
        allowanceGranted: 24,
        openingBalance: 0,
        startDate: $start,
        endDate: $start->modify('+1 year'),
    ));

    $this->ledgerRepository->add(new BalanceLedgerEntryDTO(
        id: Uuid::uuid4()->toString(),
        userId: $userId,
        leavePeriodId: $periodId,
        delta: 24,
        balanceBefore: 0,
        balanceAfter: 24,
        type: BalanceLedgerEntryTypeEnum::Accrual,
        source: BalanceLedgerSourceEnum::Scheduler,
        createdAt: new DateTimeImmutable(),
    ));

    $this->ledgerRepository->add(new BalanceLedgerEntryDTO(
        id: Uuid::uuid4()->toString(),
        userId: $userId,
        leavePeriodId: $periodId,
        delta: -5,
        balanceBefore: 24,
        balanceAfter: 19,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
        createdAt: new DateTimeImmutable(),
    ));

    $entries = $this->ledgerRepository->findForUser($userId, $periodId, 50, 0);
    $totals = $this->ledgerRepository->sumDeltasByTypeForPeriod($periodId);

    expect($entries)->toHaveCount(2)
        ->and($totals[BalanceLedgerEntryTypeEnum::Accrual->value])->toBe(24)
        ->and($totals[BalanceLedgerEntryTypeEnum::Usage->value])->toBe(-5);
});

it('orders same-timestamp entries by insertion sequence', function () {
    $userId = $this->user->id->toString();
    $start = new DateTimeImmutable('-4 months');
    $periodId = Uuid::uuid4()->toString();
    $createdAt = new DateTimeImmutable('2026-08-04 12:00:00');
    $firstId = 'ffffffff-ffff-4fff-bfff-ffffffffffff';
    $secondId = '00000000-0000-4000-8000-000000000000';

    $this->periodRepository->add(new LeavePeriodDTO(
        id: $periodId,
        userId: $userId,
        allowanceGranted: 24,
        openingBalance: 10,
        startDate: $start,
        endDate: $start->modify('+1 year'),
    ));

    $this->ledgerRepository->add(new BalanceLedgerEntryDTO(
        id: $firstId,
        userId: $userId,
        leavePeriodId: $periodId,
        delta: 5,
        balanceBefore: 10,
        balanceAfter: 15,
        type: BalanceLedgerEntryTypeEnum::ManualAdjustment,
        source: BalanceLedgerSourceEnum::App,
        createdAt: $createdAt,
        reason: 'First',
    ));
    $this->ledgerRepository->add(new BalanceLedgerEntryDTO(
        id: $secondId,
        userId: $userId,
        leavePeriodId: $periodId,
        delta: -3,
        balanceBefore: 15,
        balanceAfter: 12,
        type: BalanceLedgerEntryTypeEnum::ManualAdjustment,
        source: BalanceLedgerSourceEnum::App,
        createdAt: $createdAt,
        reason: 'Second',
    ));

    $entries = $this->ledgerRepository->findForUser($userId, $periodId, 50, 0);

    expect(array_column($entries, 'id'))->toBe([$secondId, $firstId])
        ->and(array_map(fn (BalanceLedgerEntryDTO $entry): string => $entry->createdAt->format('Y-m-d H:i:s'), $entries))
        ->toBe([$createdAt->format('Y-m-d H:i:s'), $createdAt->format('Y-m-d H:i:s')])
        ->and(array_column($entries, 'balanceBefore'))->toBe([15, 10])
        ->and(array_column($entries, 'balanceAfter'))->toBe([12, 15]);
});

it('locks a user row and returns the DTO', function () {
    $userRepository = self::getContainer()->get(UserRepository::class);
    $secondConnection = DriverManager::getConnection($this->em->getConnection()->getParams());

    $userRepository->beginTransaction();
    $secondConnection->beginTransaction();

    try {
        $locked = $userRepository->findOneByIdForUpdate($this->user->id->toString());
        $secondConnection->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');

        expect(fn () => $secondConnection->executeStatement(
            'UPDATE user SET current_leave_balance = current_leave_balance + 1 WHERE id = ?',
            [$this->user->id->toString()],
        ))->toThrow(LockWaitTimeoutException::class);

        expect($locked)->not->toBeNull()
            ->and($userRepository->isTransactionActive())->toBeTrue()
            ->and($locked->id)->toBe($this->user->id->toString());
    } finally {
        $secondConnection->rollBack();
        $secondConnection->close();

        if ($userRepository->isTransactionActive()) {
            $userRepository->rollback();
        }
    }
});

it('does not overwrite newer ledger-owned state during a generic user update', function () {
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'Stale',
        lastName: 'Balance',
        email: sprintf('stale-balance-%s@example.com', Uuid::uuid4()->toString()),
        password: 'password',
        currentLeaveBalance: 12,
    );
    $this->em->persist($user);
    $this->em->flush();

    $userRepository = self::getContainer()->get(UserRepository::class);
    $staleDTO = $userRepository->findOneById($user->id->toString());
    expect($staleDTO)->not->toBeNull();

    $newResetDay = $user->absenceBalanceResetDay->modify('+1 year');
    $user->currentLeaveBalance = 19;
    $user->absenceBalanceResetDay = $newResetDay;
    $this->em->flush();

    $staleDTO->firstName = 'Accepted';
    $userRepository->update($staleDTO);
    $this->em->clear();

    $reloaded = $this->em->find(User::class, $user->id);
    expect($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->firstName)->toBe('Accepted')
        ->and($reloaded->currentLeaveBalance)->toBe(19)
        ->and($reloaded->absenceBalanceResetDay)->toEqual($newResetDay);
});

it('rejects closing an unknown leave period', function () {
    $periodId = Uuid::uuid4()->toString();

    expect(fn () => $this->periodRepository->close($periodId, 10, new DateTimeImmutable()))
        ->toThrow(DomainException::class, sprintf('Leave period "%s" not found.', $periodId));
});
