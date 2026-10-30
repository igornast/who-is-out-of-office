<?php

declare(strict_types=1);

use App\Module\User\Repository\BalanceLedgerEntryRepositoryInterface;
use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\Repository\UserBalanceWriterInterface;
use App\Module\User\UseCase\Command\OpenInitialLeavePeriodCommandHandler;
use App\Module\User\UseCase\Command\OpenLeavePeriodCommandHandler;
use App\Shared\DTO\Balance\BalanceLedgerEntryDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Tests\_fixtures\Shared\DTO\LeavePeriodDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->leavePeriodRepository = mock(LeavePeriodRepositoryInterface::class);
    $this->userBalanceWriter = mock(UserBalanceWriterInterface::class);
    $this->ledgerRepository = mock(BalanceLedgerEntryRepositoryInterface::class);
    $this->openLeavePeriodHandler = mock(OpenLeavePeriodCommandHandler::class);

    $this->handler = new OpenInitialLeavePeriodCommandHandler(
        userRepository: $this->userRepository,
        leavePeriodRepository: $this->leavePeriodRepository,
        userBalanceWriter: $this->userBalanceWriter,
        balanceLedgerEntryRepository: $this->ledgerRepository,
        openLeavePeriodHandler: $this->openLeavePeriodHandler,
    );
});

it('opens the first period and writes an opening balance entry', function () {
    $resetDay = new DateTimeImmutable('+4 months');
    $user = UserDTOFixture::create([
        'currentLeaveBalance' => 24,
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => $resetDay,
    ]);
    $period = LeavePeriodDTOFixture::create(['userId' => $user->id]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($user->id)->andReturn($user);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with($user->id)->andReturn(null);
    $this->openLeavePeriodHandler
        ->expects('handle')
        ->once()
        ->withArgs(fn ($userDTO, DateTimeImmutable $start, DateTimeImmutable $end, int $opening): bool => $user === $userDTO
            && $start->format('Y-m-d') === $resetDay->modify('-1 year')->format('Y-m-d')
            && $end->format('Y-m-d') === $resetDay->format('Y-m-d')
            && 24 === $opening)
        ->andReturn($period);
    $this->ledgerRepository
        ->expects('add')
        ->once()
        ->withArgs(fn (BalanceLedgerEntryDTO $dto): bool => $dto->userId === $user->id
            && $dto->leavePeriodId === $period->id
            && 24 === $dto->delta
            && 0 === $dto->balanceBefore
            && 24 === $dto->balanceAfter
            && BalanceLedgerEntryTypeEnum::OpeningBalance === $dto->type
            && BalanceLedgerSourceEnum::App === $dto->source);
    $this->userRepository->expects('commit')->once();
    $this->userRepository->expects('rollback')->never();

    $this->handler->handle($user->id);

    expect($user->currentLeaveBalance)->toBe(24);
});

it('uses the annual allowance for a newly created user opening balance', function (): void {
    $resetDay = new DateTimeImmutable('+4 months');
    $user = UserDTOFixture::create([
        'annualLeaveAllowance' => 12,
        'currentLeaveBalance' => 26,
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => $resetDay,
    ]);
    $period = LeavePeriodDTOFixture::create(['userId' => $user->id, 'openingBalance' => 12]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($user->id)->andReturn($user);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with($user->id)->andReturn(null);
    $this->userBalanceWriter->expects('updateCurrentLeaveBalance')->once()->with($user->id, 12);
    $this->openLeavePeriodHandler->expects('handle')->once()->withArgs(
        fn ($userDTO, DateTimeImmutable $start, DateTimeImmutable $end, int $opening): bool => $user === $userDTO
            && $start->format('Y-m-d') === $resetDay->modify('-1 year')->format('Y-m-d')
            && $end->format('Y-m-d') === $resetDay->format('Y-m-d')
            && 12 === $opening,
    )->andReturn($period);
    $this->ledgerRepository->expects('add')->once()->withArgs(
        fn (BalanceLedgerEntryDTO $entry): bool => 12 === $entry->delta
            && 12 === $entry->balanceAfter,
    );
    $this->userRepository->expects('commit')->once();

    $this->handler->handle($user->id, true);
});

it('moves an expired reset day past account creation before opening the period', function () {
    $createdAt = new DateTimeImmutable('-1 day');
    $resetDay = new DateTimeImmutable('-1 year');
    $user = UserDTOFixture::create([
        'createdAt' => $createdAt,
        'absenceBalanceResetDay' => $resetDay,
    ]);
    $period = LeavePeriodDTOFixture::create(['userId' => $user->id]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($user);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->andReturn(null);
    $this->userBalanceWriter->expects('updateAbsenceBalanceResetDay')->once()->withArgs(
        fn (string $userId, DateTimeImmutable $newResetDay): bool => $userId === $user->id
            && $newResetDay > $createdAt,
    );
    $this->openLeavePeriodHandler->expects('handle')->once()->andReturn($period);
    $this->ledgerRepository->expects('add')->once();
    $this->userRepository->expects('commit')->once();

    $this->handler->handle($user->id);
});

it('does nothing when the user cannot be found', function () {
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with('missing')->andReturn(null);
    $this->leavePeriodRepository->expects('findOpenForUser')->never();
    $this->openLeavePeriodHandler->expects('handle')->never();
    $this->ledgerRepository->expects('add')->never();
    $this->userRepository->expects('commit')->once();
    $this->userRepository->expects('rollback')->never();

    $this->handler->handle('missing');
});

it('does not create a duplicate when the user already has an open period', function () {
    $user = UserDTOFixture::create();

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($user);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with($user->id)->andReturn(LeavePeriodDTOFixture::create());
    $this->openLeavePeriodHandler->expects('handle')->never();
    $this->ledgerRepository->expects('add')->never();
    $this->userRepository->expects('commit')->once();
    $this->userRepository->expects('rollback')->never();

    $this->handler->handle($user->id);
});

it('rolls back when the opening entry cannot be written', function () {
    $user = UserDTOFixture::create(['createdAt' => new DateTimeImmutable('-1 year')]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($user);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->andReturn(null);
    $this->openLeavePeriodHandler->expects('handle')->once()->andReturn(LeavePeriodDTOFixture::create());
    $this->ledgerRepository->expects('add')->once()->andThrow(new RuntimeException('boom'));
    $this->userRepository->expects('commit')->never();
    $this->userRepository->expects('rollback')->once();

    expect(fn () => $this->handler->handle($user->id))->toThrow(RuntimeException::class, 'boom');
});

it('joins an existing transaction without committing it', function () {
    $user = UserDTOFixture::create(['createdAt' => new DateTimeImmutable('-1 year')]);
    $period = LeavePeriodDTOFixture::create(['userId' => $user->id]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(true);
    $this->userRepository->expects('beginTransaction')->never();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($user);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->andReturn(null);
    $this->openLeavePeriodHandler->expects('handle')->once()->andReturn($period);
    $this->ledgerRepository->expects('add')->once();
    $this->userRepository->expects('commit')->never();
    $this->userRepository->expects('rollback')->never();

    $this->handler->handle($user->id);
});

it('leaves rollback to the owner of an existing transaction', function () {
    $user = UserDTOFixture::create(['createdAt' => new DateTimeImmutable('-1 year')]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(true);
    $this->userRepository->expects('beginTransaction')->never();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($user);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->andReturn(null);
    $this->openLeavePeriodHandler->expects('handle')->once()->andReturn(LeavePeriodDTOFixture::create());
    $this->ledgerRepository->expects('add')->once()->andThrow(new RuntimeException('boom'));
    $this->userRepository->expects('commit')->never();
    $this->userRepository->expects('rollback')->never();

    expect(fn () => $this->handler->handle($user->id))->toThrow(RuntimeException::class, 'boom');
});
