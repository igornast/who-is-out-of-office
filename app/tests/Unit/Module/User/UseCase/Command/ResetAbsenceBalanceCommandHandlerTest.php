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
    ]);

    $user2 = UserDTOFixture::create([
        'annualLeaveAllowance' => 30,
        'currentLeaveBalance' => 10,
        'absenceBalanceResetDay' => $resetDate2,
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
