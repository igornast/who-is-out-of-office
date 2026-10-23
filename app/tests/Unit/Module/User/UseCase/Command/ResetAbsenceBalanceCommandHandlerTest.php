<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\ResetAbsenceBalanceCommandHandler;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->logger = mock(LoggerInterface::class);
    $this->logger->allows('info');

    $this->handler = new ResetAbsenceBalanceCommandHandler(
        userRepository: $this->userRepository,
        logger: $this->logger,
    );
});

it('resets balance and advances reset date for users', function () {
    $resetDate1 = new DateTimeImmutable('2026-01-01');
    $resetDate2 = new DateTimeImmutable('2026-02-07');

    $user1 = UserDTOFixture::create([
        'annualLeaveAllowance' => 24,
        'currentLeaveBalance' => 5,
        'absenceBalanceResetDay' => $resetDate1,
        'createdAt' => new DateTimeImmutable('2020-06-15'),
    ]);

    $user2 = UserDTOFixture::create([
        'annualLeaveAllowance' => 30,
        'currentLeaveBalance' => 10,
        'absenceBalanceResetDay' => $resetDate2,
        'createdAt' => new DateTimeImmutable('2020-06-15'),
    ]);

    $this->userRepository
        ->expects('findUsersWithBalanceResetToday')
        ->once()
        ->andReturn([$user1, $user2]);

    $this->userRepository
        ->expects('update')
        ->twice();

    $this->handler->handle();

    expect($user1->currentLeaveBalance)->toBe(29)
        ->and($user1->absenceBalanceResetDay->format('Y-m-d'))->toBe('2027-01-01')
        ->and($user2->currentLeaveBalance)->toBe(40)
        ->and($user2->absenceBalanceResetDay->format('Y-m-d'))->toBe('2027-02-07');

    $this->logger->shouldHaveReceived('info')
        ->with('[USER][BALANCE-RESET]: Done. Reset 2 users.');
});

it('does nothing when no users have reset day today', function () {
    $this->userRepository
        ->expects('findUsersWithBalanceResetToday')
        ->once()
        ->andReturn([]);

    $this->userRepository
        ->expects('update')
        ->never();

    $this->handler->handle();
});

it('skips the allowance and moves the reset day past account creation', function () {
    $createdAt = new DateTimeImmutable('first day of last month 09:00');
    $user = UserDTOFixture::create([
        'annualLeaveAllowance' => 24,
        'currentLeaveBalance' => 24,
        'createdAt' => $createdAt,
        'absenceBalanceResetDay' => $createdAt->modify('-2 years'),
    ]);

    $this->userRepository
        ->expects('findUsersWithBalanceResetToday')
        ->once()
        ->andReturn([$user]);

    $this->userRepository
        ->expects('update')
        ->once()
        ->with($user);

    $this->handler->handle();

    expect($user->currentLeaveBalance)->toBe(24)
        ->and($user->absenceBalanceResetDay->format('Y-m-d'))->toBe($createdAt->modify('+1 year')->format('Y-m-d'));

    $this->logger->shouldHaveReceived('info')
        ->with('[USER][BALANCE-RESET]: Done. Reset 0 users.');
});

it('skips the allowance when the reset day is the account creation day', function () {
    $createdAt = new DateTimeImmutable('-1 day 14:30');
    $user = UserDTOFixture::create([
        'annualLeaveAllowance' => 24,
        'currentLeaveBalance' => 24,
        'createdAt' => $createdAt,
        'absenceBalanceResetDay' => $createdAt->setTime(0, 0),
    ]);

    $this->userRepository
        ->expects('findUsersWithBalanceResetToday')
        ->once()
        ->andReturn([$user]);

    $this->userRepository
        ->expects('update')
        ->once()
        ->with($user);

    $this->handler->handle();

    expect($user->currentLeaveBalance)->toBe(24)
        ->and($user->absenceBalanceResetDay->format('Y-m-d'))->toBe($createdAt->modify('+1 year')->format('Y-m-d'));
});
