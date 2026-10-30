<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\LeavePeriod;
use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Module\User\UseCase\Command\ResetAbsenceBalanceCommandHandler;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\LeaveRequest\Command\SaveLeaveRequestCommand;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    self::bootKernel();
    $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    $this->userFacade = self::getContainer()->get(UserFacadeInterface::class);
    $this->leaveRequestFacade = self::getContainer()->get(LeaveRequestFacadeInterface::class);
});

it('writes a usage entry linked to the request when a booking is made', function (): void {
    $user = createLedgerLifecycleUser($this->entityManager, new DateTimeImmutable('+6 months'));
    $userId = $user->id->toString();
    $balanceBefore = $user->currentLeaveBalance;
    $this->userFacade->openInitialLeavePeriod($userId);
    $userDTO = $this->userFacade->getUser($userId);
    expect($userDTO)->not->toBeNull();
    $start = new DateTimeImmutable('next monday');

    $this->leaveRequestFacade->save(new SaveLeaveRequestCommand(
        userDTO: $userDTO,
        leaveRequestTypeDTO: findBalanceAffectingLeaveType($this->leaveRequestFacade),
        startDate: $start,
        endDate: $start->modify('+2 days'),
    ));

    $this->entityManager->clear();

    $entries = $this->entityManager->getRepository(BalanceLedgerEntry::class)
        ->findBy(['user' => $userId, 'type' => BalanceLedgerEntryTypeEnum::Usage], ['createdAt' => 'DESC'], 1);
    $reloaded = $this->entityManager->find(User::class, $userId);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->leaveRequest)->not->toBeNull()
        ->and($entries[0]->source)->toBe(BalanceLedgerSourceEnum::App)
        ->and($entries[0]->delta)->toBeLessThan(0)
        ->and($entries[0]->balanceBefore)->toBe($balanceBefore)
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($entries[0]->balanceAfter)->toBe($reloaded->currentLeaveBalance)
        ->and($entries[0]->leavePeriod)->not->toBeNull();
});

it('grants the new-user allowance exactly once when reset is already due', function (): void {
    $today = new DateTimeImmutable('today');
    $user = createLedgerLifecycleUser($this->entityManager, $today, 24, 24, new DateTimeImmutable('-1 day'));
    $userId = $user->id->toString();

    self::getContainer()->get(ResetAbsenceBalanceCommandHandler::class)->handle();
    $userDTO = $this->userFacade->getUser($userId);
    expect($userDTO)->not->toBeNull();
    $balanceAfterReset = $userDTO->currentLeaveBalance;

    $this->userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
        userId: $userId,
        delta: -1,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
    ));

    $this->entityManager->clear();
    $entries = $this->entityManager->getRepository(BalanceLedgerEntry::class)->findBy([
        'user' => $userId,
        'type' => BalanceLedgerEntryTypeEnum::Accrual,
    ]);
    $reloaded = $this->entityManager->find(User::class, $userId);

    expect($entries)->toHaveCount(1)
        ->and($reloaded->currentLeaveBalance)->toBe($balanceAfterReset - 1)
        ->and($balanceAfterReset)->toBe(48);
});

it('books through the facade without accruing when reset day is on account creation', function (): void {
    $today = new DateTimeImmutable('today');
    $user = createLedgerLifecycleUser($this->entityManager, $today, 24, 24, $today);
    $userId = $user->id->toString();

    self::getContainer()->get(ResetAbsenceBalanceCommandHandler::class)->handle();
    $userDTO = $this->userFacade->getUser($userId);
    expect($userDTO)->not->toBeNull();
    $start = new DateTimeImmutable('next monday');

    $this->leaveRequestFacade->save(new SaveLeaveRequestCommand(
        userDTO: $userDTO,
        leaveRequestTypeDTO: findBalanceAffectingLeaveType($this->leaveRequestFacade),
        startDate: $start,
        endDate: $start,
    ));

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(User::class, $userId);
    $entryRepository = $this->entityManager->getRepository(BalanceLedgerEntry::class);
    $accruals = $entryRepository->findBy(['user' => $userId, 'type' => BalanceLedgerEntryTypeEnum::Accrual]);
    $entries = $entryRepository->findBy(['user' => $userId]);

    expect($accruals)->toHaveCount(0)
        ->and($reloaded->currentLeaveBalance)->toBe(23)
        ->and(array_sum(array_map(fn (BalanceLedgerEntry $entry): int => $entry->delta, $entries)))->toBe(23);
});

it('persists the moved reset day without granting a new-user allowance', function (): void {
    $today = new DateTimeImmutable('today');
    $user = createLedgerLifecycleUser($this->entityManager, $today, 24, 24, $today);
    $userId = $user->id->toString();

    self::getContainer()->get(ResetAbsenceBalanceCommandHandler::class)->handle();

    $this->entityManager->clear();
    $reloaded = $this->entityManager->find(User::class, $userId);
    $entryRepository = $this->entityManager->getRepository(BalanceLedgerEntry::class);
    $accruals = $entryRepository->findBy([
        'user' => $userId,
        'type' => BalanceLedgerEntryTypeEnum::Accrual,
    ]);

    expect($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->absenceBalanceResetDay->format('Y-m-d'))->toBe($today->modify('+1 year')->format('Y-m-d'))
        ->and($accruals)->toHaveCount(0);

    $balanceAfterFirstRun = $reloaded->currentLeaveBalance;
    $resetDayAfterFirstRun = $reloaded->absenceBalanceResetDay;

    self::getContainer()->get(ResetAbsenceBalanceCommandHandler::class)->handle();

    $this->entityManager->clear();
    $secondRunAccruals = $entryRepository->findBy([
        'user' => $userId,
        'type' => BalanceLedgerEntryTypeEnum::Accrual,
    ]);

    expect($secondRunAccruals)->toHaveCount(0);
    $reloadedAfterSecondRun = $this->entityManager->find(User::class, $userId);
    expect($reloadedAfterSecondRun->currentLeaveBalance)->toBe($balanceAfterFirstRun)
        ->and($reloadedAfterSecondRun->absenceBalanceResetDay)->toEqual($resetDayAfterFirstRun);
});

it('attributes January absence dates to the period open before the year boundary', function (): void {
    $resetDay = new DateTimeImmutable('first day of January next year');
    $user = createLedgerLifecycleUser($this->entityManager, $resetDay);
    $userId = $user->id->toString();
    $this->userFacade->openInitialLeavePeriod($userId);
    $userDTO = $this->userFacade->getUser($userId);
    expect($userDTO)->not->toBeNull();
    $absenceStart = $resetDay->modify('+1 week');

    expect($resetDay->format('m-d'))->toBe('01-01')
        ->and($absenceStart->format('m'))->toBe('01')
        ->and(new DateTimeImmutable('today'))->toBeLessThan($resetDay);

    $this->leaveRequestFacade->save(new SaveLeaveRequestCommand(
        userDTO: $userDTO,
        leaveRequestTypeDTO: findBalanceAffectingLeaveType($this->leaveRequestFacade),
        startDate: $absenceStart,
        endDate: $absenceStart->modify('+1 day'),
    ));

    $this->entityManager->clear();

    $entries = $this->entityManager->getRepository(BalanceLedgerEntry::class)
        ->findBy(['user' => $userId, 'type' => BalanceLedgerEntryTypeEnum::Usage], ['createdAt' => 'DESC'], 1);

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->leavePeriod->endDate->format('Y-m-d'))->toBe($resetDay->format('Y-m-d'))
        ->and($entries[0]->leavePeriod->startDate)->toBeLessThan(new DateTimeImmutable('tomorrow'))
        ->and($entries[0]->leaveRequest?->startDate)->toBeGreaterThan($entries[0]->leavePeriod->endDate);
});

it('closes the period opens the next one and records accrual on reset', function (): void {
    $tomorrow = new DateTimeImmutable('tomorrow');
    $resetDates = [];

    try {
        foreach (self::getContainer()->get(UserRepository::class)->findUsersWithBalanceResetToday() as $candidate) {
            $existingUser = $this->entityManager->find(User::class, $candidate->id);
            expect($existingUser)->toBeInstanceOf(User::class);
            $resetDates[$candidate->id] = $existingUser->absenceBalanceResetDay;
            $existingUser->absenceBalanceResetDay = $tomorrow;
        }

        $today = new DateTimeImmutable('today');
        $user = createLedgerLifecycleUser($this->entityManager, $today, 27, 13, new DateTimeImmutable('-1 day'));
        $userId = $user->id->toString();
        $this->entityManager->flush();
        $this->userFacade->openInitialLeavePeriod($userId);
        $this->entityManager->clear();

        $candidateIds = array_map(
            fn ($candidate): string => $candidate->id,
            self::getContainer()->get(UserRepository::class)->findUsersWithBalanceResetToday(),
        );
        expect($candidateIds)->toContain($userId);

        self::getContainer()->get(ResetAbsenceBalanceCommandHandler::class)->handle();

        $this->entityManager->clear();

        $periods = $this->entityManager->getRepository(LeavePeriod::class)
            ->findBy(['user' => $userId], ['startDate' => 'DESC']);
        $reloaded = $this->entityManager->find(User::class, $userId);
        $accruals = $this->entityManager->getRepository(BalanceLedgerEntry::class)
            ->findBy(['user' => $userId, 'type' => BalanceLedgerEntryTypeEnum::Accrual]);
        $newest = $periods[0];
        $closed = null;
        foreach ($periods as $period) {
            if (null !== $period->closedAt) {
                $closed = $period;
                break;
            }
        }

        expect($closed)->toBeInstanceOf(LeavePeriod::class)
            ->and($closed->closingBalance)->toBe(13)
            ->and($newest->closedAt)->toBeNull()
            ->and($newest->openingBalance)->toBe(13)
            ->and($newest->startDate->format('Y-m-d'))->toBe($today->format('Y-m-d'))
            ->and($accruals)->toHaveCount(1)
            ->and($accruals[0]->delta)->toBe(27)
            ->and($accruals[0]->source)->toBe(BalanceLedgerSourceEnum::Scheduler)
            ->and($accruals[0]->leavePeriod->id->toString())->toBe($newest->id->toString())
            ->and($reloaded)->toBeInstanceOf(User::class)
            ->and($reloaded->currentLeaveBalance)->toBe(40)
            ->and($reloaded->absenceBalanceResetDay->format('Y-m-d'))->toBe($today->modify('+1 year')->format('Y-m-d'));
    } finally {
        $this->entityManager->clear();
        foreach ($resetDates as $userId => $resetDate) {
            $existingUser = $this->entityManager->find(User::class, $userId);
            if ($existingUser instanceof User) {
                $existingUser->absenceBalanceResetDay = $resetDate;
            }
        }
        $this->entityManager->flush();
    }

    foreach ($resetDates as $userId => $resetDate) {
        $restoredUser = $this->entityManager->find(User::class, $userId);
        expect($restoredUser)->toBeInstanceOf(User::class)
            ->and($restoredUser->absenceBalanceResetDay->format('Y-m-d'))->toBe($resetDate->format('Y-m-d'));
    }
});

it('rolls an expired period before recording the first reset-day change', function (): void {
    $today = new DateTimeImmutable('today');
    $user = createLedgerLifecycleUser($this->entityManager, $today, 24, 3, new DateTimeImmutable('-1 day'));
    $userId = $user->id->toString();
    $this->userFacade->openInitialLeavePeriod($userId);

    $this->userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
        userId: $userId,
        delta: -2,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
    ));

    $this->entityManager->clear();

    $periods = $this->entityManager->getRepository(LeavePeriod::class)
        ->findBy(['user' => $userId], ['startDate' => 'ASC']);
    $entryRepository = $this->entityManager->getRepository(BalanceLedgerEntry::class);
    $entries = $entryRepository->findBy(['user' => $userId]);
    $accrual = $entryRepository->findOneBy(['user' => $userId, 'type' => BalanceLedgerEntryTypeEnum::Accrual]);
    $usage = $entryRepository->findOneBy(['user' => $userId, 'type' => BalanceLedgerEntryTypeEnum::Usage]);
    $reloaded = $this->entityManager->find(User::class, $userId);

    expect($periods)->toHaveCount(2)
        ->and($periods[0]->closedAt)->not->toBeNull()
        ->and($periods[0]->closingBalance)->toBe(3)
        ->and($periods[1]->startDate->format('Y-m-d'))->toBe($today->format('Y-m-d'))
        ->and($entries)->toHaveCount(3)
        ->and($accrual)->toBeInstanceOf(BalanceLedgerEntry::class)
        ->and($accrual->leavePeriod->id->toString())->toBe($periods[1]->id->toString())
        ->and($usage)->toBeInstanceOf(BalanceLedgerEntry::class)
        ->and($usage->leavePeriod->id->toString())->toBe($periods[1]->id->toString())
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->currentLeaveBalance)->toBe(25)
        ->and($reloaded->absenceBalanceResetDay->format('Y-m-d'))->toBe($today->modify('+1 year')->format('Y-m-d'));
});

it('opens one rollover period without accrual when its start predates account creation', function (): void {
    $today = new DateTimeImmutable('today');
    $user = createLedgerLifecycleUser($this->entityManager, new DateTimeImmutable('tomorrow'), 24, 3, $today);
    $userId = $user->id->toString();
    $expiredPeriod = new LeavePeriod(
        allowanceGranted: 24,
        openingBalance: 3,
        id: Uuid::uuid4(),
        user: $user,
        startDate: $today->modify('-2 years'),
        endDate: $today->modify('-1 day'),
    );
    $this->entityManager->persist($expiredPeriod);
    $this->entityManager->flush();

    $this->userFacade->recordBalanceChange(new RecordBalanceChangeCommand(
        userId: $userId,
        delta: -2,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
    ));

    $this->entityManager->clear();
    $periods = $this->entityManager->getRepository(LeavePeriod::class)
        ->findBy(['user' => $userId], ['startDate' => 'ASC']);
    $entryRepository = $this->entityManager->getRepository(BalanceLedgerEntry::class);
    $accruals = $entryRepository->findBy([
        'user' => $userId,
        'type' => BalanceLedgerEntryTypeEnum::Accrual,
    ]);
    $reloaded = $this->entityManager->find(User::class, $userId);

    expect($periods)->toHaveCount(2)
        ->and($periods[0]->closedAt)->not->toBeNull()
        ->and($periods[1]->closedAt)->toBeNull()
        ->and($periods[1]->startDate->format('Y-m-d'))->toBe($today->modify('-1 day')->format('Y-m-d'))
        ->and($accruals)->toHaveCount(0)
        ->and($reloaded)->toBeInstanceOf(User::class)
        ->and($reloaded->currentLeaveBalance)->toBe(1);
});

it('grants every missed allowance during a multi-year scheduled catch-up', function (): void {
    $tomorrow = new DateTimeImmutable('tomorrow');
    $resetDates = [];

    try {
        foreach (self::getContainer()->get(UserRepository::class)->findUsersWithBalanceResetToday() as $candidate) {
            $existingUser = $this->entityManager->find(User::class, $candidate->id);
            expect($existingUser)->toBeInstanceOf(User::class);
            $resetDates[$candidate->id] = $existingUser->absenceBalanceResetDay;
            $existingUser->absenceBalanceResetDay = $tomorrow;
        }

        $firstMissedReset = new DateTimeImmutable('-1 year');
        $user = createLedgerLifecycleUser($this->entityManager, $firstMissedReset, 24, 3, new DateTimeImmutable('-2 years'));
        $userId = $user->id->toString();
        $this->entityManager->flush();
        $this->userFacade->openInitialLeavePeriod($userId);

        self::getContainer()->get(ResetAbsenceBalanceCommandHandler::class)->handle();
        $this->entityManager->clear();

        $periods = $this->entityManager->getRepository(LeavePeriod::class)
            ->findBy(['user' => $userId], ['startDate' => 'ASC']);
        $accruals = $this->entityManager->getRepository(BalanceLedgerEntry::class)
            ->findBy(['user' => $userId, 'type' => BalanceLedgerEntryTypeEnum::Accrual], ['sequence' => 'ASC']);
        $reloaded = $this->entityManager->find(User::class, $userId);

        expect($periods)->toHaveCount(3)
            ->and($accruals)->toHaveCount(2)
            ->and(array_map(fn (BalanceLedgerEntry $entry): int => $entry->delta, $accruals))->toBe([24, 24])
            ->and($accruals[0]->getCreatedAt()->format('Y-m-d'))->toBe($firstMissedReset->format('Y-m-d'))
            ->and($reloaded)->toBeInstanceOf(User::class)
            ->and($reloaded->currentLeaveBalance)->toBe(51)
            ->and($reloaded->absenceBalanceResetDay->format('Y-m-d'))->toBe($firstMissedReset->modify('+2 years')->format('Y-m-d'));
    } finally {
        $this->entityManager->clear();
        foreach ($resetDates as $userId => $resetDate) {
            $existingUser = $this->entityManager->find(User::class, $userId);
            if ($existingUser instanceof User) {
                $existingUser->absenceBalanceResetDay = $resetDate;
            }
        }
        $this->entityManager->flush();
    }
});

function createLedgerLifecycleUser(
    EntityManagerInterface $entityManager,
    DateTimeImmutable $resetDay,
    int $allowance = 24,
    int $balance = 24,
    ?DateTimeImmutable $createdAt = null,
): User {
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'Ledger',
        lastName: 'Lifecycle',
        email: sprintf('ledger-lifecycle-%s@example.com', Uuid::uuid4()->toString()),
        password: 'password',
        isActive: true,
        annualLeaveAllowance: $allowance,
        currentLeaveBalance: $balance,
        absenceBalanceResetDay: $resetDay,
    );
    if (null !== $createdAt) {
        $user->setCreatedAt($createdAt);
    }
    $entityManager->persist($user);
    $entityManager->flush();

    return $user;
}

function findBalanceAffectingLeaveType(LeaveRequestFacadeInterface $leaveRequestFacade): LeaveRequestTypeDTO
{
    foreach ($leaveRequestFacade->getAllLeaveTypes() as $leaveType) {
        if ($leaveType->isAffectingBalance) {
            return $leaveType;
        }
    }

    throw new RuntimeException('No balance-affecting leave type found.');
}
