<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\Holiday;
use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Doctrine\Entity\UserSlackIntegration;
use App\Infrastructure\Doctrine\Repository\SlackHolidayStatusRepository;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    self::bootKernel();
    $this->em = self::getContainer()->get(EntityManagerInterface::class);
    $this->connection = $this->em->getConnection();
    $this->repository = self::getContainer()->get(SlackHolidayStatusRepository::class);
    $this->today = new DateTimeImmutable('today');
    $this->yesterday = $this->today->modify('-1 day');
    $this->tomorrow = $this->today->modify('+1 day');
    $this->emails = [];
    $this->userIds = [];
    $this->createUser = function (string $role, ?string $subdivisionCode = null, bool $isActive = true, bool $syncEnabled = true, bool $withSlack = true, ?HolidayCalendar $calendar = null): User {
        $user = new User(
            id: Uuid::uuid4(),
            firstName: 'Holiday',
            lastName: $role,
            email: sprintf('holiday-status-%s@test.local', $role),
            password: 'password',
            isActive: $isActive,
            subdivisionCode: $subdivisionCode,
            holidayCalendar: $calendar ?? $this->zwCalendar,
        );
        $this->em->persist($user);
        if ($withSlack) {
            $integration = new UserSlackIntegration($user, sprintf('U-HS-%s', $role), $syncEnabled);
            $user->slackIntegration = $integration;
            $this->em->persist($integration);
        }

        return $user;
    };

    $this->zwCalendar = new HolidayCalendar(Uuid::uuid4(), 'ZW', 'Zedland');
    $this->zvCalendar = new HolidayCalendar(Uuid::uuid4(), 'ZV', 'Vland', isActive: false);
    $this->em->persist($this->zwCalendar);
    $this->em->persist($this->zvCalendar);
    $this->em->flush();

    $this->zetaHoliday = new Holiday(Uuid::uuid4(), 'Zeta Day', $this->today, $this->zwCalendar);
    $this->regionHoliday = new Holiday(Uuid::uuid4(), 'Region Day', $this->today, $this->zwCalendar, false, ['ZW-A']);
    $this->veeHoliday = new Holiday(Uuid::uuid4(), 'Vee Day', $this->today, $this->zvCalendar);
    $this->yesterdayHoliday = new Holiday(Uuid::uuid4(), 'Yesterday Day', $this->yesterday, $this->zwCalendar);
    $this->tomorrowHoliday = new Holiday(Uuid::uuid4(), 'Tomorrow Day', $this->tomorrow, $this->zwCalendar);
    $this->nullRegionHoliday = new Holiday(Uuid::uuid4(), 'Null Region Day', $this->tomorrow, $this->zwCalendar, false, null);
    $this->em->persist($this->zetaHoliday);
    $this->em->persist($this->regionHoliday);
    $this->em->persist($this->veeHoliday);
    $this->em->persist($this->yesterdayHoliday);
    $this->em->persist($this->tomorrowHoliday);
    $this->em->persist($this->nullRegionHoliday);

    $users = [];
    $users['plain'] = ($this->createUser)('plain');
    $users['regionA'] = ($this->createUser)('regionA', subdivisionCode: 'ZW-A');
    $users['regionB'] = ($this->createUser)('regionB', subdivisionCode: 'ZW-B');
    $users['inactive'] = ($this->createUser)('inactive', isActive: false);
    $users['syncOff'] = ($this->createUser)('syncOff', syncEnabled: false);
    $users['noSlack'] = ($this->createUser)('noSlack', withSlack: false);
    $users['onLeave'] = ($this->createUser)('onLeave');
    $users['veeUser'] = ($this->createUser)('veeUser', calendar: $this->zvCalendar);
    $users['gone'] = ($this->createUser)('gone', withSlack: false);
    $users['leaveEndsToday'] = ($this->createUser)('leaveEndsToday');
    $users['leaveStartsToday'] = ($this->createUser)('leaveStartsToday');
    $users['leaveEndedYesterday'] = ($this->createUser)('leaveEndedYesterday');
    $users['leavePending'] = ($this->createUser)('leavePending');
    $users['leaveNotSynced'] = ($this->createUser)('leaveNotSynced');

    $leaveType = $this->em->getRepository(LeaveRequestType::class)->findOneBy(['name' => 'Vacation']);
    expect($leaveType)->toBeInstanceOf(LeaveRequestType::class);
    $createLeave = fn (User $user, LeaveRequestStatusEnum $status, DateTimeImmutable $startDate, DateTimeImmutable $endDate, bool $isExternalStatusSynced = true) => $this->em->persist(new LeaveRequest(
        id: Uuid::uuid4(),
        user: $user,
        status: $status,
        leaveType: $leaveType,
        startDate: $startDate,
        endDate: $endDate,
        isExternalStatusSynced: $isExternalStatusSynced,
    ));
    $createLeave($users['onLeave'], LeaveRequestStatusEnum::Approved, $this->today->modify('-1 day'), $this->today->modify('+1 day'));
    $createLeave($users['leaveEndsToday'], LeaveRequestStatusEnum::Approved, $this->today->modify('-3 days'), $this->today);
    $createLeave($users['leaveStartsToday'], LeaveRequestStatusEnum::Approved, $this->today, $this->today->modify('+2 days'));
    $createLeave($users['leaveEndedYesterday'], LeaveRequestStatusEnum::Approved, $this->today->modify('-5 days'), $this->yesterday);
    $createLeave($users['leavePending'], LeaveRequestStatusEnum::Pending, $this->today->modify('-1 day'), $this->today->modify('+1 day'));
    $createLeave($users['leaveNotSynced'], LeaveRequestStatusEnum::Approved, $this->today->modify('-1 day'), $this->today->modify('+1 day'), false);

    $this->em->flush();

    $this->users = $users;
    foreach ($users as $role => $user) {
        $this->emails[$role] = $user->email;
        $this->userIds[$role] = $user->id->toString();
    }
    $this->holidayIds = [
        'zeta' => $this->zetaHoliday->id->toString(),
        'region' => $this->regionHoliday->id->toString(),
        'vee' => $this->veeHoliday->id->toString(),
        'yesterday' => $this->yesterdayHoliday->id->toString(),
        'tomorrow' => $this->tomorrowHoliday->id->toString(),
        'nullRegion' => $this->nullRegionHoliday->id->toString(),
    ];
});

afterEach(function (): void {
    foreach ($this->userIds as $userId) {
        $this->connection->executeStatement('DELETE FROM slack_holiday_status WHERE user_id = ?', [$userId]);
        $this->connection->executeStatement('DELETE FROM leave_request WHERE user_id = ?', [$userId]);
        $this->connection->executeStatement('DELETE FROM user_slack_integration WHERE user_id = ?', [$userId]);
    }
    foreach ($this->emails as $email) {
        $this->connection->executeStatement('DELETE FROM user WHERE email = ?', [$email]);
    }
    foreach ($this->holidayIds as $holidayId) {
        $this->connection->executeStatement('DELETE FROM holiday WHERE id = ?', [$holidayId]);
    }
    $this->connection->executeStatement('DELETE FROM holiday_calendar WHERE country_code IN (?, ?)', ['ZW', 'ZV']);
    self::ensureKernelShutdown();
});

it('returns global and regional holidays for a user without subdivision', function (): void {
    $results = $this->repository->findCandidatesForDay($this->today);
    $candidate = array_values(array_filter($results, fn ($item) => $item->user->id === $this->userIds['plain']))[0];

    expect($candidate->countryCode)->toBe('ZW')
        ->and(array_map(fn ($holiday) => $holiday->description, $candidate->holidays))
        ->toEqualCanonicalizing(['Zeta Day', 'Region Day']);
});

it('returns regional holidays only for matching subdivisions', function (): void {
    $results = $this->repository->findCandidatesForDay($this->today);
    $byUser = [];
    foreach ($results as $candidate) {
        $byUser[$candidate->user->id] = array_map(fn ($holiday) => $holiday->description, $candidate->holidays);
    }

    expect($byUser[$this->userIds['regionA']])->toEqualCanonicalizing(['Zeta Day', 'Region Day'])
        ->and($byUser[$this->userIds['regionB']])->toEqual(['Zeta Day']);
});

it('excludes inactive users, disabled sync, missing slack integration and synced leave', function (): void {
    $results = $this->repository->findCandidatesForDay($this->today);
    $userIds = array_map(fn ($candidate) => $candidate->user->id, $results);

    expect($userIds)->toContain($this->userIds['plain'])
        ->and($userIds)->not->toContain($this->userIds['inactive'])
        ->and($userIds)->not->toContain($this->userIds['syncOff'])
        ->and($userIds)->not->toContain($this->userIds['noSlack'])
        ->and($userIds)->not->toContain($this->userIds['onLeave']);
});

it('excludes holidays from inactive calendars', function (): void {
    $results = $this->repository->findCandidatesForDay($this->today);
    $userIds = array_map(fn ($candidate) => $candidate->user->id, $results);

    expect($userIds)->toContain($this->userIds['plain'])
        ->and($userIds)->not->toContain($this->userIds['veeUser']);
});

it('returns slack member id on candidate users', function (): void {
    $results = $this->repository->findCandidatesForDay($this->today);
    $candidate = array_values(array_filter($results, fn ($item) => $item->user->id === $this->userIds['plain']))[0];

    expect($candidate->user->slackMemberId)->toBe('U-HS-plain');
});

it('returns one candidate per user with every applicable holiday', function (): void {
    $results = $this->repository->findCandidatesForDay($this->today);
    $plainCandidates = array_values(array_filter($results, fn ($item) => $item->user->id === $this->userIds['plain']));

    expect($plainCandidates)->toHaveCount(1)
        ->and(array_map(fn ($holiday) => $holiday->id, $plainCandidates[0]->holidays))
        ->toEqualCanonicalizing([$this->holidayIds['zeta'], $this->holidayIds['region']]);
});

it('returns only holidays dated on the requested day', function (): void {
    $todayResults = $this->repository->findCandidatesForDay($this->today);
    $tomorrowResults = $this->repository->findCandidatesForDay($this->tomorrow);
    $todayCandidate = array_values(array_filter($todayResults, fn ($item) => $item->user->id === $this->userIds['plain']))[0];
    $tomorrowCandidate = array_values(array_filter($tomorrowResults, fn ($item) => $item->user->id === $this->userIds['plain']))[0];

    expect(array_map(fn ($holiday) => $holiday->description, $todayCandidate->holidays))
        ->toEqualCanonicalizing(['Zeta Day', 'Region Day'])
        ->and(array_map(fn ($holiday) => $holiday->description, $tomorrowCandidate->holidays))
        ->toEqualCanonicalizing(['Tomorrow Day', 'Null Region Day']);
});

it('applies a regional holiday without counties only to users without a subdivision', function (): void {
    $results = $this->repository->findCandidatesForDay($this->tomorrow);
    $byUser = [];
    foreach ($results as $candidate) {
        $byUser[$candidate->user->id] = array_map(fn ($holiday) => $holiday->description, $candidate->holidays);
    }

    expect($byUser[$this->userIds['plain']])->toEqualCanonicalizing(['Tomorrow Day', 'Null Region Day'])
        ->and($byUser[$this->userIds['regionA']])->toEqual(['Tomorrow Day'])
        ->and($byUser[$this->userIds['regionB']])->toEqual(['Tomorrow Day']);
});

it('excludes a user whose synced approved leave ends today', function (): void {
    $userId = $this->userIds['leaveEndsToday'];
    $this->repository->upsert($userId, $this->holidayIds['zeta'], $this->today, 'Zeta Day');

    $candidateUserIds = array_map(fn ($candidate) => $candidate->user->id, $this->repository->findCandidatesForDay($this->today));
    $row = array_values(array_filter($this->repository->findAllTracked($this->today), fn ($item) => $item->user->id === $userId))[0];

    expect($candidateUserIds)->not->toContain($userId)
        ->and($row->isUserOnSyncedLeave)->toBeTrue();
});

it('excludes a user whose synced approved leave starts today', function (): void {
    $userId = $this->userIds['leaveStartsToday'];
    $this->repository->upsert($userId, $this->holidayIds['zeta'], $this->today, 'Zeta Day');

    $candidateUserIds = array_map(fn ($candidate) => $candidate->user->id, $this->repository->findCandidatesForDay($this->today));
    $row = array_values(array_filter($this->repository->findAllTracked($this->today), fn ($item) => $item->user->id === $userId))[0];

    expect($candidateUserIds)->not->toContain($userId)
        ->and($row->isUserOnSyncedLeave)->toBeTrue();
});

it('keeps a user whose synced approved leave ended yesterday', function (): void {
    $userId = $this->userIds['leaveEndedYesterday'];
    $this->repository->upsert($userId, $this->holidayIds['zeta'], $this->today, 'Zeta Day');

    $candidateUserIds = array_map(fn ($candidate) => $candidate->user->id, $this->repository->findCandidatesForDay($this->today));
    $row = array_values(array_filter($this->repository->findAllTracked($this->today), fn ($item) => $item->user->id === $userId))[0];

    expect($candidateUserIds)->toContain($userId)
        ->and($row->isUserOnSyncedLeave)->toBeFalse();
});

it('keeps a user whose only leave covering today is pending', function (): void {
    $userId = $this->userIds['leavePending'];
    $this->repository->upsert($userId, $this->holidayIds['zeta'], $this->today, 'Zeta Day');

    $candidateUserIds = array_map(fn ($candidate) => $candidate->user->id, $this->repository->findCandidatesForDay($this->today));
    $row = array_values(array_filter($this->repository->findAllTracked($this->today), fn ($item) => $item->user->id === $userId))[0];

    expect($candidateUserIds)->toContain($userId)
        ->and($row->isUserOnSyncedLeave)->toBeFalse();
});

it('keeps a user whose approved leave covering today is not synced', function (): void {
    $userId = $this->userIds['leaveNotSynced'];
    $this->repository->upsert($userId, $this->holidayIds['zeta'], $this->today, 'Zeta Day');

    $candidateUserIds = array_map(fn ($candidate) => $candidate->user->id, $this->repository->findCandidatesForDay($this->today));
    $row = array_values(array_filter($this->repository->findAllTracked($this->today), fn ($item) => $item->user->id === $userId))[0];

    expect($candidateUserIds)->toContain($userId)
        ->and($row->isUserOnSyncedLeave)->toBeFalse();
});

it('upserts one row per user and day', function (): void {
    $userId = $this->userIds['plain'];
    $this->repository->upsert($userId, $this->holidayIds['zeta'], $this->today, 'First');
    $before = $this->connection->fetchAssociative(
        'SELECT id, created_at, status_text FROM slack_holiday_status WHERE user_id = ? AND date = ?',
        [$userId, $this->today->format('Y-m-d')],
    );
    $this->repository->upsert($userId, $this->holidayIds['region'], $this->today, 'Second');
    $after = $this->connection->fetchAssociative(
        'SELECT id, created_at, status_text FROM slack_holiday_status WHERE user_id = ? AND date = ?',
        [$userId, $this->today->format('Y-m-d')],
    );

    $rows = $this->repository->findAllTracked($this->today);
    $plainRows = array_values(array_filter($rows, fn ($item) => $item->user->id === $userId));

    expect($plainRows)->toHaveCount(1)
        ->and($plainRows[0]->statusText)->toBe('Second')
        ->and($plainRows[0]->holidayId)->toBe($this->holidayIds['region'])
        ->and($before)->not->toBeFalse()
        ->and($after)->not->toBeFalse()
        ->and($after['id'])->toBe($before['id'])
        ->and($after['created_at'])->toBe($before['created_at'])
        ->and($after['status_text'])->toBe('Second');
});

it('keeps id and created_at but refreshes updated_at when a status is upserted again', function (): void {
    $userId = $this->userIds['plain'];
    $day = $this->today->format('Y-m-d');
    $oldTimestamp = $this->today->modify('-1 year')->format('Y-m-d H:i:s');
    $this->repository->upsert($userId, $this->holidayIds['zeta'], $this->today, 'First');
    $first = $this->connection->fetchAssociative('SELECT id FROM slack_holiday_status WHERE user_id = ? AND date = ?', [$userId, $day]);
    $this->connection->executeStatement(
        'UPDATE slack_holiday_status SET created_at = ?, updated_at = ? WHERE user_id = ? AND date = ?',
        [$oldTimestamp, $oldTimestamp, $userId, $day],
    );

    $this->repository->upsert($userId, $this->holidayIds['region'], $this->today, 'Second');
    $after = $this->connection->fetchAssociative(
        'SELECT id, created_at, updated_at, status_text FROM slack_holiday_status WHERE user_id = ? AND date = ?',
        [$userId, $day],
    );

    expect($after['id'])->toBe($first['id'])
        ->and($after['created_at'])->toBe($oldTimestamp)
        ->and($after['updated_at'])->not->toBe($oldTimestamp)
        ->and($after['status_text'])->toBe('Second');
});

it('stores a null holiday when the holiday no longer exists', function (): void {
    $this->repository->upsert($this->userIds['plain'], Uuid::uuid4()->toString(), $this->today, 'Unknown');
    $rows = $this->repository->findAllTracked($this->today);
    $row = array_values(array_filter($rows, fn ($item) => $item->user->id === $this->userIds['plain']))[0];

    expect($row->holidayId)->toBeNull();
});

it('keeps the tracking row with a null holiday when the holiday is deleted', function (): void {
    $this->repository->upsert($this->userIds['plain'], $this->holidayIds['zeta'], $this->today, 'Zeta Day');
    $this->connection->executeStatement('DELETE FROM holiday WHERE id = ?', [$this->holidayIds['zeta']]);
    $this->em->clear();

    $rows = $this->repository->findAllTracked($this->today);
    $row = array_values(array_filter($rows, fn ($item) => $item->user->id === $this->userIds['plain']))[0];

    expect($row->holidayId)->toBeNull();
});

it('removes the tracking row when its user is deleted', function (): void {
    $userId = $this->userIds['gone'];
    $this->repository->upsert($userId, $this->holidayIds['zeta'], $this->today, 'Zeta Day');
    $countBefore = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM slack_holiday_status WHERE user_id = ?', [$userId]);

    $this->connection->executeStatement('DELETE FROM user WHERE id = ?', [$userId]);
    $countAfter = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM slack_holiday_status WHERE user_id = ?', [$userId]);

    expect($countBefore)->toBe(1)
        ->and($countAfter)->toBe(0);
});

it('returns tracked rows dated in the past at midnight', function (): void {
    $this->repository->upsert($this->userIds['plain'], $this->holidayIds['yesterday'], $this->yesterday, 'Yesterday Day');
    $this->repository->upsert($this->userIds['regionA'], $this->holidayIds['zeta'], $this->today, 'Zeta Day');

    $rows = $this->repository->findAllTracked($this->today);
    $ownRows = array_values(array_filter($rows, fn ($item) => in_array($item->user->id, [$this->userIds['plain'], $this->userIds['regionA']], true)));
    $pastRow = array_values(array_filter($ownRows, fn ($item) => $item->user->id === $this->userIds['plain']))[0];
    $todayRow = array_values(array_filter($ownRows, fn ($item) => $item->user->id === $this->userIds['regionA']))[0];

    expect($ownRows)->toHaveCount(2)
        ->and($pastRow->date->format('Y-m-d'))->toBe($this->yesterday->format('Y-m-d'))
        ->and($pastRow->date->format('H:i:s'))->toBe('00:00:00')
        ->and($pastRow->holidayId)->toBe($this->holidayIds['yesterday'])
        ->and($todayRow->date->format('Y-m-d'))->toBe($this->today->format('Y-m-d'));
});

it('exposes slack sync state and member id of tracked users', function (): void {
    $this->repository->upsert($this->userIds['syncOff'], $this->holidayIds['zeta'], $this->today, 'Zeta Day');
    $this->repository->upsert($this->userIds['noSlack'], $this->holidayIds['zeta'], $this->today, 'Zeta Day');

    $rows = $this->repository->findAllTracked($this->today);
    $syncOffRow = array_values(array_filter($rows, fn ($item) => $item->user->id === $this->userIds['syncOff']))[0];
    $noSlackRow = array_values(array_filter($rows, fn ($item) => $item->user->id === $this->userIds['noSlack']))[0];

    expect($syncOffRow->user->slackStatusSyncEnabled)->toBeFalse()
        ->and($syncOffRow->user->slackMemberId)->toBe('U-HS-syncOff')
        ->and($noSlackRow->user->slackMemberId)->toBeNull();
});

it('flags tracked rows of users on synced leave', function (): void {
    $this->repository->upsert($this->userIds['plain'], $this->holidayIds['zeta'], $this->today, 'Zeta Day');
    $this->repository->upsert($this->userIds['onLeave'], $this->holidayIds['zeta'], $this->today, 'Zeta Day');
    $rows = $this->repository->findAllTracked($this->today);
    $onLeaveRow = array_values(array_filter($rows, fn ($item) => $item->user->id === $this->userIds['onLeave']))[0];
    $plainRow = array_values(array_filter($rows, fn ($item) => $item->user->id === $this->userIds['plain']))[0];

    expect($onLeaveRow->isUserOnSyncedLeave)->toBeTrue()
        ->and($plainRow->isUserOnSyncedLeave)->toBeFalse();
});

it('deletes a tracked row', function (): void {
    $this->repository->upsert($this->userIds['plain'], $this->holidayIds['zeta'], $this->today, 'Zeta Day');
    $rows = $this->repository->findAllTracked($this->today);
    $row = array_values(array_filter($rows, fn ($item) => $item->user->id === $this->userIds['plain']))[0];
    $this->repository->delete($row->id);

    $rows = $this->repository->findAllTracked($this->today);

    expect(array_filter($rows, fn ($item) => $item->id === $row->id))->toBe([]);
});
