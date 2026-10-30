<?php

declare(strict_types=1);

use App\Module\User\Repository\BalanceLedgerEntryRepositoryInterface;
use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Module\User\Repository\UserBalanceWriterInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\OpenLeavePeriodCommandHandler;
use App\Module\User\UseCase\Command\OpenInitialLeavePeriodCommandHandler;
use App\Module\User\UseCase\Command\CloseLeavePeriodCommandHandler;
use App\Module\User\UseCase\Command\RecordBalanceChangeCommandHandler;
use App\Shared\DTO\Balance\BalanceLedgerEntryDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Exception\InsufficientLeaveBalanceException;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use App\Tests\_fixtures\Shared\DTO\LeavePeriodDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->userBalanceWriter = mock(UserBalanceWriterInterface::class);
    $this->leavePeriodRepository = mock(LeavePeriodRepositoryInterface::class);
    $this->ledgerRepository = mock(BalanceLedgerEntryRepositoryInterface::class);
    $this->openLeavePeriodHandler = mock(OpenLeavePeriodCommandHandler::class);
    $this->openInitialLeavePeriodHandler = mock(OpenInitialLeavePeriodCommandHandler::class);
    $this->closeLeavePeriodHandler = mock(CloseLeavePeriodCommandHandler::class);

    $this->handler = new RecordBalanceChangeCommandHandler(
        userRepository: $this->userRepository,
        userBalanceWriter: $this->userBalanceWriter,
        leavePeriodRepository: $this->leavePeriodRepository,
        balanceLedgerEntryRepository: $this->ledgerRepository,
        openInitialLeavePeriodHandler: $this->openInitialLeavePeriodHandler,
        openLeavePeriodHandler: $this->openLeavePeriodHandler,
        closeLeavePeriodHandler: $this->closeLeavePeriodHandler,
    );
});

it('opens its own transaction, records the entry and writes the new balance', function () {
    $userDTO = UserDTOFixture::create(['currentLeaveBalance' => 10]);
    $period = LeavePeriodDTOFixture::create(['userId' => $userDTO->id]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($userDTO->id)->andReturn($userDTO);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with($userDTO->id)->andReturn($period);

    $this->ledgerRepository
        ->expects('add')
        ->once()
        ->withArgs(fn (BalanceLedgerEntryDTO $dto): bool => $dto->userId === $userDTO->id
                && $dto->leavePeriodId === $period->id
                && -3 === $dto->delta
                && 10 === $dto->balanceBefore
                && 7 === $dto->balanceAfter
                && BalanceLedgerEntryTypeEnum::Usage === $dto->type
                && BalanceLedgerSourceEnum::App === $dto->source
                && 'Medical appointment' === $dto->reason
                && 'actor-1' === $dto->actorUserId
                && 'request-1' === $dto->leaveRequestId);

    $this->userBalanceWriter->expects('updateCurrentLeaveBalance')->once()->with($userDTO->id, 7);
    $this->userRepository->expects('commit')->once();

    $this->handler->handle(new RecordBalanceChangeCommand(
        userId: $userDTO->id,
        delta: -3,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
        reason: 'Medical appointment',
        actorUserId: 'actor-1',
        leaveRequestId: 'request-1',
    ));

    expect($userDTO->currentLeaveBalance)->toBe(7);
});

it('joins an existing transaction instead of opening its own', function () {
    $userDTO = UserDTOFixture::create(['currentLeaveBalance' => 10]);
    $period = LeavePeriodDTOFixture::create(['userId' => $userDTO->id]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(true);
    $this->userRepository->expects('beginTransaction')->never();
    $this->userRepository->expects('commit')->never();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($userDTO);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->andReturn($period);
    $this->ledgerRepository->expects('add')->once();
    $this->userBalanceWriter->expects('updateCurrentLeaveBalance')->once()->with($userDTO->id, 12);

    $this->handler->handle(new RecordBalanceChangeCommand(
        userId: $userDTO->id,
        delta: 2,
        type: BalanceLedgerEntryTypeEnum::Restoration,
        source: BalanceLedgerSourceEnum::App,
    ));

    expect($userDTO->currentLeaveBalance)->toBe(12);
});

it('opens a period lazily when the user has none', function () {
    $resetDay = new DateTimeImmutable('+2 months');
    $userDTO = UserDTOFixture::create([
        'currentLeaveBalance' => 4,
        'absenceBalanceResetDay' => $resetDay,
    ]);
    $period = LeavePeriodDTOFixture::create(['userId' => $userDTO->id]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(true);
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($userDTO);
    $this->leavePeriodRepository->expects('findOpenForUser')->twice()->andReturn(null, $period);
    $this->openInitialLeavePeriodHandler->expects('handle')->once()->with($userDTO->id);
    $this->ledgerRepository->expects('add')->once();
    $this->userBalanceWriter->expects('updateCurrentLeaveBalance')->once()->with($userDTO->id, 3);

    $this->handler->handle(new RecordBalanceChangeCommand(
        userId: $userDTO->id,
        delta: -1,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
    ));

    expect($userDTO->currentLeaveBalance)->toBe(3);
});

it('rolls back when no period can be opened for the user', function () {
    $userDTO = UserDTOFixture::create(['currentLeaveBalance' => 4]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($userDTO);
    $this->leavePeriodRepository->expects('findOpenForUser')->twice()->with($userDTO->id)->andReturn(null, null);
    $this->openInitialLeavePeriodHandler->expects('handle')->once()->with($userDTO->id);
    $this->ledgerRepository->expects('add')->never();
    $this->userBalanceWriter->expects('updateCurrentLeaveBalance')->never();
    $this->userRepository->expects('rollback')->once();
    $this->userRepository->expects('commit')->never();

    expect(fn () => $this->handler->handle(new RecordBalanceChangeCommand(
        userId: $userDTO->id,
        delta: -1,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
    )))->toThrow(DomainException::class, sprintf('Could not open leave period for user "%s".', $userDTO->id));

    expect($userDTO->currentLeaveBalance)->toBe(4);
});

it('rejects a manual adjustment with a blank reason', function () {
    $this->userRepository->expects('isTransactionActive')->never();
    $this->userRepository->expects('findOneByIdForUpdate')->never();
    $this->ledgerRepository->expects('add')->never();
    $this->userBalanceWriter->expects('updateCurrentLeaveBalance')->never();

    $this->handler->handle(new RecordBalanceChangeCommand(
        userId: 'user-1',
        delta: 5,
        type: BalanceLedgerEntryTypeEnum::ManualAdjustment,
        source: BalanceLedgerSourceEnum::App,
        reason: '   ',
    ));
})->throws(DomainException::class);

it('rejects usage that exceeds the locked current balance', function () {
    $userDTO = UserDTOFixture::create(['currentLeaveBalance' => 2]);
    $period = LeavePeriodDTOFixture::create(['userId' => $userDTO->id]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($userDTO);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->andReturn($period);
    $this->ledgerRepository->expects('add')->never();
    $this->userBalanceWriter->expects('updateCurrentLeaveBalance')->never();
    $this->userRepository->expects('rollback')->once();
    $this->userRepository->expects('commit')->never();

    $this->handler->handle(new RecordBalanceChangeCommand(
        userId: $userDTO->id,
        delta: -3,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
    ));
})->throws(InsufficientLeaveBalanceException::class, 'Insufficient leave balance.');

it('throws when the user does not exist', function () {
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(true);
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn(null);

    $this->handler->handle(new RecordBalanceChangeCommand(
        userId: 'missing',
        delta: 1,
        type: BalanceLedgerEntryTypeEnum::Accrual,
        source: BalanceLedgerSourceEnum::Scheduler,
    ));
})->throws(DomainException::class);

it('rolls back its own transaction when the ledger insert fails', function () {
    $userDTO = UserDTOFixture::create(['currentLeaveBalance' => 10]);
    $period = LeavePeriodDTOFixture::create(['userId' => $userDTO->id]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($userDTO);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->andReturn($period);
    $this->ledgerRepository->expects('add')->once()->andThrow(new RuntimeException('insert failed'));
    $this->userBalanceWriter->expects('updateCurrentLeaveBalance')->never();
    $this->userRepository->expects('rollback')->once();
    $this->userRepository->expects('commit')->never();

    $this->handler->handle(new RecordBalanceChangeCommand(
        userId: $userDTO->id,
        delta: -1,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
    ));
})->throws(RuntimeException::class);

it('leaves an outer transaction active when the ledger insert fails', function () {
    $userDTO = UserDTOFixture::create(['currentLeaveBalance' => 10]);
    $period = LeavePeriodDTOFixture::create(['userId' => $userDTO->id]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(true);
    $this->userRepository->expects('beginTransaction')->never();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($userDTO);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->andReturn($period);
    $this->ledgerRepository->expects('add')->once()->andThrow(new RuntimeException('insert failed'));
    $this->userRepository->expects('rollback')->never();
    $this->userRepository->expects('commit')->never();

    $this->handler->handle(new RecordBalanceChangeCommand(
        userId: $userDTO->id,
        delta: -1,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
    ));
})->throws(RuntimeException::class);

it('restores the in-memory balance and rolls back when updating the user fails', function () {
    $userDTO = UserDTOFixture::create(['currentLeaveBalance' => 10]);
    $period = LeavePeriodDTOFixture::create(['userId' => $userDTO->id]);

    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->andReturn($userDTO);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->andReturn($period);
    $this->ledgerRepository->expects('add')->once();
    $this->userBalanceWriter->expects('updateCurrentLeaveBalance')->once()->andThrow(new RuntimeException('update failed'));
    $this->userRepository->expects('rollback')->once();
    $this->userRepository->expects('commit')->never();

    try {
        $this->handler->handle(new RecordBalanceChangeCommand(
            userId: $userDTO->id,
            delta: -3,
            type: BalanceLedgerEntryTypeEnum::Usage,
            source: BalanceLedgerSourceEnum::App,
        ));
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('update failed');
    }

    expect($userDTO->currentLeaveBalance)->toBe(7);
});
