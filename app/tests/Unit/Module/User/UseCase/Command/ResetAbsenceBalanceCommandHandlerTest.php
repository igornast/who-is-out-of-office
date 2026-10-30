<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Module\User\Repository\UserBalanceWriterInterface;
use App\Module\User\UseCase\Command\CloseLeavePeriodCommandHandler;
use App\Module\User\UseCase\Command\OpenLeavePeriodCommandHandler;
use App\Module\User\UseCase\Command\RecordBalanceChangeCommandHandler;
use App\Module\User\UseCase\Command\ResetAbsenceBalanceCommandHandler;
use App\Shared\DTO\Balance\LeavePeriodDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use App\Tests\_fixtures\Shared\DTO\LeavePeriodDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->leavePeriodRepository = mock(LeavePeriodRepositoryInterface::class);
    $this->userBalanceWriter = mock(UserBalanceWriterInterface::class);
    $this->closeLeavePeriodHandler = mock(CloseLeavePeriodCommandHandler::class);
    $this->openLeavePeriodHandler = mock(OpenLeavePeriodCommandHandler::class);
    $this->recordBalanceChangeHandler = mock(RecordBalanceChangeCommandHandler::class);
    $this->logger = mock(LoggerInterface::class);
    $this->logger->allows('info');
    $this->logger->allows('error');
    $this->leavePeriodRepository->expects('findOpenForUser')->zeroOrMoreTimes()->andReturn(null)->byDefault();

    $this->handler = new ResetAbsenceBalanceCommandHandler(
        userRepository: $this->userRepository,
        leavePeriodRepository: $this->leavePeriodRepository,
        userBalanceWriter: $this->userBalanceWriter,
        closeLeavePeriodHandler: $this->closeLeavePeriodHandler,
        openLeavePeriodHandler: $this->openLeavePeriodHandler,
        recordBalanceChangeHandler: $this->recordBalanceChangeHandler,
        logger: $this->logger,
    );
});

it('delegates the complete rollover and accrual to the locked balance funnel', function () {
    $resetDay = new DateTimeImmutable('today');
    $user = UserDTOFixture::create([
        'annualLeaveAllowance' => 24,
        'currentLeaveBalance' => 3,
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => $resetDay,
    ]);
    $updatedUser = clone $user;
    $updatedUser->absenceBalanceResetDay = $resetDay->modify('+1 year');

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$user]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->atLeast()->twice()->with($user->id)->andReturn($user, $updatedUser);
    $this->userRepository->expects('commit')->once();
    $this->userRepository->expects('rollback')->never();

    $this->recordBalanceChangeHandler
        ->expects('handle')
        ->once()
        ->withArgs(fn (RecordBalanceChangeCommand $command): bool => $command->userId === $user->id
            && 24 === $command->delta
            && BalanceLedgerEntryTypeEnum::Accrual === $command->type
            && BalanceLedgerSourceEnum::Scheduler === $command->source
            && null === $command->actorUserId);

    $this->handler->handle();

    expect($user->currentLeaveBalance)->toBe(3)
        ->and($updatedUser->absenceBalanceResetDay->format('Y-m-d'))->toBe($resetDay->modify('+1 year')->format('Y-m-d'));
});

it('does nothing when no users have reset day today', function () {
    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([]);
    $this->userRepository->expects('isTransactionActive')->never();
    $this->userRepository->expects('beginTransaction')->never();
    $this->recordBalanceChangeHandler->expects('handle')->never();

    $this->handler->handle();
});

it('skips the allowance and moves the reset day past account creation', function () {
    $createdAt = new DateTimeImmutable('first day of last month 09:00');
    $candidate = UserDTOFixture::create([
        'createdAt' => $createdAt,
        'absenceBalanceResetDay' => $createdAt->modify('-2 years'),
    ]);
    $lockedUser = clone $candidate;

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$candidate]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($candidate->id)->andReturn($lockedUser);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with($lockedUser->id)->andReturn(null);
    $this->userBalanceWriter->expects('updateAbsenceBalanceResetDay')->once()->withArgs(
        fn (string $userId, DateTimeImmutable $resetDay): bool => $userId === $lockedUser->id
            && $resetDay->format('Y-m-d') === $createdAt->modify('+1 year')->format('Y-m-d'),
    );
    $this->userRepository->expects('commit')->once();
    $this->recordBalanceChangeHandler->expects('handle')->never();

    $this->handler->handle();

    expect($lockedUser->currentLeaveBalance)->toBe($candidate->currentLeaveBalance)
        ->and($lockedUser->absenceBalanceResetDay->format('Y-m-d'))->toBe($createdAt->modify('+1 year')->format('Y-m-d'));
});

it('skips the allowance when the reset day is the account creation day', function () {
    $createdAt = new DateTimeImmutable('-1 day 14:30');
    $candidate = UserDTOFixture::create([
        'createdAt' => $createdAt,
        'absenceBalanceResetDay' => $createdAt->setTime(0, 0),
    ]);
    $lockedUser = clone $candidate;

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$candidate]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($candidate->id)->andReturn($lockedUser);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with($lockedUser->id)->andReturn(null);
    $this->userBalanceWriter->expects('updateAbsenceBalanceResetDay')->once()->withArgs(
        fn (string $userId, DateTimeImmutable $resetDay): bool => $userId === $lockedUser->id
            && $resetDay->format('Y-m-d') === $createdAt->modify('+1 year')->format('Y-m-d'),
    );
    $this->userRepository->expects('commit')->once();
    $this->recordBalanceChangeHandler->expects('handle')->never();

    $this->handler->handle();

    expect($lockedUser->currentLeaveBalance)->toBe($candidate->currentLeaveBalance)
        ->and($lockedUser->absenceBalanceResetDay->format('Y-m-d'))->toBe($createdAt->modify('+1 year')->format('Y-m-d'));
});

it('moves an open period with the reset day without adding an allowance', function (): void {
    $createdAt = new DateTimeImmutable('today 14:30');
    $oldResetDay = $createdAt->modify('-2 years')->setTime(0, 0);
    $candidate = UserDTOFixture::create([
        'createdAt' => $createdAt,
        'absenceBalanceResetDay' => $oldResetDay,
        'currentLeaveBalance' => 7,
    ]);
    $lockedUser = clone $candidate;
    $openPeriod = new LeavePeriodDTO(
        id: 'period-id',
        userId: $lockedUser->id,
        allowanceGranted: 24,
        openingBalance: 7,
        startDate: $oldResetDay->modify('-1 year'),
        endDate: $oldResetDay,
    );
    $movedPeriod = new LeavePeriodDTO(
        id: 'moved-period-id',
        userId: $lockedUser->id,
        allowanceGranted: 24,
        openingBalance: 7,
        startDate: $createdAt->setTime(0, 0),
        endDate: $createdAt->modify('+1 year')->setTime(0, 0),
    );

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$candidate]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($candidate->id)->andReturn($lockedUser);
    $this->userBalanceWriter->expects('updateAbsenceBalanceResetDay')->once()->withArgs(
        fn (string $userId, DateTimeImmutable $resetDay): bool => $userId === $lockedUser->id
            && $resetDay->format('Y-m-d') === $createdAt->modify('+1 year')->format('Y-m-d'),
    );
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with($lockedUser->id)->andReturn($openPeriod);
    $this->closeLeavePeriodHandler->expects('handle')->once()->with($lockedUser->id, 7);
    $this->openLeavePeriodHandler->expects('handle')->once()->withArgs(
        fn (UserDTO $user, DateTimeImmutable $startDate, DateTimeImmutable $endDate, int $openingBalance): bool => $user === $lockedUser
            && $startDate->format('Y-m-d') === $oldResetDay->modify('+2 years')->format('Y-m-d')
            && $endDate->format('Y-m-d') === $createdAt->modify('+1 year')->format('Y-m-d')
            && 7 === $openingBalance,
    )->andReturn($movedPeriod);
    $this->recordBalanceChangeHandler->expects('handle')->never();
    $this->userRepository->expects('commit')->once();

    $this->handler->handle();

    expect($lockedUser->absenceBalanceResetDay->format('Y-m-d'))
        ->toBe($createdAt->modify('+1 year')->format('Y-m-d'));
});

it('realigns an open period when its end does not match the old reset day', function (): void {
    $createdAt = new DateTimeImmutable('today 14:30');
    $oldResetDay = $createdAt->setTime(0, 0);
    $candidate = UserDTOFixture::create([
        'createdAt' => $createdAt,
        'absenceBalanceResetDay' => $oldResetDay,
        'currentLeaveBalance' => 7,
    ]);
    $lockedUser = clone $candidate;
    $openPeriod = new LeavePeriodDTO(
        id: 'period-id',
        userId: $lockedUser->id,
        allowanceGranted: 24,
        openingBalance: 7,
        startDate: $oldResetDay->modify('-1 year'),
        endDate: $oldResetDay->modify('+1 day'),
    );
    $movedPeriod = new LeavePeriodDTO(
        id: 'moved-period-id',
        userId: $lockedUser->id,
        allowanceGranted: 24,
        openingBalance: 7,
        startDate: $oldResetDay,
        endDate: $oldResetDay->modify('+1 year'),
    );

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$candidate]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($candidate->id)->andReturn($lockedUser);
    $this->userBalanceWriter->expects('updateAbsenceBalanceResetDay')->once();
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with($lockedUser->id)->andReturn($openPeriod);
    $this->closeLeavePeriodHandler->expects('handle')->once()->with($lockedUser->id, 7);
    $this->openLeavePeriodHandler->expects('handle')->once()->andReturn($movedPeriod);
    $this->recordBalanceChangeHandler->expects('handle')->never();
    $this->userRepository->expects('commit')->once();

    $this->handler->handle();
});

it('processes a due candidate when repository and entity times differ on the same date', function () {
    $candidate = UserDTOFixture::create([
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => new DateTimeImmutable('today 15:00:00'),
    ]);
    $lockedUser = UserDTOFixture::create([
        'id' => $candidate->id,
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => new DateTimeImmutable('today 00:00:00'),
    ]);
    $updatedUser = clone $lockedUser;
    $updatedUser->absenceBalanceResetDay = new DateTimeImmutable('+1 year');

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$candidate]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->atLeast()->twice()->with($candidate->id)->andReturn($lockedUser, $updatedUser);
    $this->recordBalanceChangeHandler->expects('handle')->once();
    $this->userRepository->expects('commit')->once();
    $this->userRepository->expects('rollback')->never();

    $this->handler->handle();

    expect($updatedUser->absenceBalanceResetDay->format('Y-m-d'))
        ->toBe((new DateTimeImmutable('today'))->modify('+1 year')->format('Y-m-d'));
});

it('rolls back and continues when the accrual fails', function () {
    $resetDay = new DateTimeImmutable('today');
    $user = UserDTOFixture::create([
        'annualLeaveAllowance' => 24,
        'currentLeaveBalance' => 3,
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => $resetDay,
    ]);

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$user]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($user->id)->andReturn($user);
    $this->recordBalanceChangeHandler->expects('handle')->once()->andThrow(new RuntimeException('boom'));
    $this->userRepository->expects('rollback')->once();
    $this->userRepository->expects('commit')->never();

    $this->handler->handle();
    expect($user->absenceBalanceResetDay)->toBe($resetDay)
        ->and($user->currentLeaveBalance)->toBe(3);
    $this->logger->shouldHaveReceived('error')->once();
});

it('does not complete an outer transaction when the accrual fails', function () {
    $user = UserDTOFixture::create([
        'annualLeaveAllowance' => 24,
        'currentLeaveBalance' => 3,
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => new DateTimeImmutable('today'),
    ]);

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$user]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(true);
    $this->userRepository->expects('beginTransaction')->never();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($user->id)->andReturn($user);
    $this->recordBalanceChangeHandler->expects('handle')->once()->andThrow(new RuntimeException('boom'));
    $this->userRepository->expects('rollback')->never();
    $this->userRepository->expects('commit')->never();

    $this->handler->handle();
    $this->logger->shouldHaveReceived('error')->once();
});

it('skips a stale candidate whose reset was completed by another worker', function () {
    $candidate = UserDTOFixture::create([
        'absenceBalanceResetDay' => new DateTimeImmutable('-2 years'),
    ]);
    $lockedUser = UserDTOFixture::create([
        'id' => $candidate->id,
        'absenceBalanceResetDay' => new DateTimeImmutable('-1 year'),
    ]);

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$candidate]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($candidate->id)->andReturn($lockedUser);
    $this->recordBalanceChangeHandler->expects('handle')->never();
    $this->userRepository->expects('commit')->once();
    $this->userRepository->expects('rollback')->never();
    $this->handler->handle();

    $this->logger
        ->shouldHaveReceived('info')
        ->with('[USER][BALANCE-RESET]: Done. Reset 0 users.')
        ->once();
});

it('continues processing after one user fails', function (): void {
    $first = UserDTOFixture::create([
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => new DateTimeImmutable('today'),
    ]);
    $second = UserDTOFixture::create([
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => new DateTimeImmutable('today'),
    ]);

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$first, $second]);
    $this->userRepository->expects('isTransactionActive')->twice()->andReturn(false, false);
    $this->userRepository->expects('beginTransaction')->twice();
    $this->userRepository->expects('findOneByIdForUpdate')->times(3)->andReturn($first, $second, $second);
    $this->userRepository->expects('rollback')->once();
    $this->userRepository->expects('commit')->once();
    $this->recordBalanceChangeHandler->expects('handle')->twice()->andReturnUsing(
        static function (RecordBalanceChangeCommand $command) use ($first): void {
            if ($command->userId === $first->id) {
                throw new RuntimeException('boom');
            }
        },
    );

    $this->handler->handle();

    $this->logger->shouldHaveReceived('error')->once();
    $this->logger->shouldHaveReceived('info')->with('[USER][BALANCE-RESET]: Done. Reset 1 users.')->once();
});

it('rolls back and logs when the locked user no longer exists', function (): void {
    $candidate = UserDTOFixture::create([
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => new DateTimeImmutable('today'),
    ]);

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$candidate]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($candidate->id)->andReturn(null);
    $this->recordBalanceChangeHandler->expects('handle')->never();
    $this->userRepository->expects('rollback')->once();
    $this->userRepository->expects('commit')->never();

    $this->handler->handle();

    $this->logger->shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => '[USER][BALANCE-RESET]: Failed to reset user.' === $message
            && $context['user_id'] === $candidate->id
            && $context['exception'] instanceof DomainException)
        ->once();
    $this->logger->shouldHaveReceived('info')->with('[USER][BALANCE-RESET]: Done. Reset 0 users.')->once();
});

it('realigns a misaligned open period on a due reset day without adding the allowance', function (): void {
    $resetDay = new DateTimeImmutable('today');
    $user = UserDTOFixture::create([
        'annualLeaveAllowance' => 24,
        'currentLeaveBalance' => 5,
        'createdAt' => new DateTimeImmutable('-2 years'),
        'absenceBalanceResetDay' => $resetDay,
    ]);
    $openPeriod = LeavePeriodDTOFixture::create([
        'userId' => $user->id,
        'startDate' => $resetDay->modify('-1 year +1 month'),
        'endDate' => $resetDay->modify('+1 month'),
    ]);

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$user]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->once()->with($user->id)->andReturn($user);
    $this->leavePeriodRepository->expects('findOpenForUser')->once()->with($user->id)->andReturn($openPeriod);
    $this->closeLeavePeriodHandler->expects('handle')->once()->with($user->id, 5);
    $this->openLeavePeriodHandler->expects('handle')->once()->withArgs(
        fn (UserDTO $periodUser, DateTimeImmutable $startDate, DateTimeImmutable $endDate, int $openingBalance): bool => $periodUser === $user
            && $startDate->format('Y-m-d') === $resetDay->modify('-1 year')->format('Y-m-d')
            && $endDate->format('Y-m-d') === $resetDay->format('Y-m-d')
            && 5 === $openingBalance,
    )->andReturn(LeavePeriodDTOFixture::create(['userId' => $user->id]));
    $this->userBalanceWriter->expects('updateAbsenceBalanceResetDay')->never();
    $this->recordBalanceChangeHandler->expects('handle')->never();
    $this->userRepository->expects('commit')->once();
    $this->userRepository->expects('rollback')->never();

    $this->handler->handle();

    $this->logger->shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => '[USER][BALANCE-RESET]: Open period end differs from reset day.' === $message
            && $context['period_end'] === $resetDay->modify('+1 month')->format('Y-m-d')
            && $context['reset_day'] === $resetDay->format('Y-m-d'))
        ->once();
    $this->logger->shouldHaveReceived('info')->with('[USER][BALANCE-RESET]: Done. Reset 0 users.')->once();
    expect($user->currentLeaveBalance)->toBe(5);
});

it('rolls back when the user disappears after the accrual', function (): void {
    $user = UserDTOFixture::create([
        'createdAt' => new DateTimeImmutable('-1 year'),
        'absenceBalanceResetDay' => new DateTimeImmutable('today'),
    ]);

    $this->userRepository->expects('findUsersWithBalanceResetToday')->once()->andReturn([$user]);
    $this->userRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->userRepository->expects('beginTransaction')->once();
    $this->userRepository->expects('findOneByIdForUpdate')->twice()->with($user->id)->andReturn($user, null);
    $this->recordBalanceChangeHandler->expects('handle')->once();
    $this->userRepository->expects('rollback')->once();
    $this->userRepository->expects('commit')->never();

    $this->handler->handle();

    $this->logger->shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => '[USER][BALANCE-RESET]: Failed to reset user.' === $message
            && $context['exception'] instanceof DomainException)
        ->once();
    $this->logger->shouldHaveReceived('info')->with('[USER][BALANCE-RESET]: Done. Reset 0 users.')->once();
});
