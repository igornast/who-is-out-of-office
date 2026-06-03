<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;

/*
 * Functional tests for GET+POST /app/api/user/calendar/customize
 *
 * Fixture setup (from src/DataFixtures/fixtures.yaml):
 *   - user_2 (Petra Schmidt, manager@whoisooo.app): ROLE_MANAGER, holiday_calendar_de
 *     Direct reports (have manager=user_2): user_12 (Elena Novak) and user_13 (David Kim),
 *       both ROLE_MANAGER sub-managers who in turn manage user_3..user_6 and others.
 *
 * Stateless CSRF pattern (SameOriginCsrfTokenManager):
 *   - Sending HTTP_ORIGIN: http://localhost makes isValidOrigin() return true.
 *   - Token value 'csrf-token' (== cookieName) bypasses double-submit length check.
 *   - Together they satisfy isCsrfTokenValid() without a session or cookie.
 */
beforeEach(function (): void {
    $this->client = static::createClient();
    $this->em = static::getContainer()->get('doctrine')->getManager();

    $this->user = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);
});

it('GET returns payload shape with non-empty candidateTeamMembers having id/name/email', function (): void {
    $this->client->loginUser($this->user);

    $this->client->request('GET', '/app/api/user/calendar/customize');

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $data = json_decode($this->client->getResponse()->getContent(), true);

    expect($data)->toHaveKey('candidateTeamMembers')
        ->toHaveKey('candidateHolidayCalendars')
        ->toHaveKey('topLevelTeamMemberIds')
        ->toHaveKey('selectedTeamMemberIds')
        ->toHaveKey('selectedHolidayCalendarIds');

    expect($data['candidateTeamMembers'])->not->toBeEmpty();

    foreach ($data['candidateTeamMembers'] as $member) {
        expect($member)->toHaveKey('id')
            ->toHaveKey('name')
            ->toHaveKey('email')
            ->toHaveKey('isManager')
            ->toHaveKey('reportIds')
            ->toHaveKey('avatarUrl')
            ->toHaveKey('initials')
            ->toHaveKey('colorIndex');
    }
});

it('GET resolves avatarUrl, initials and colorIndex for candidates', function (): void {
    $this->client->loginUser($this->user);
    $this->client->request('GET', '/app/api/user/calendar/customize');

    $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

    foreach ($data['candidateTeamMembers'] as $member) {
        expect($member['initials'])->toBeString()
            ->and(strlen($member['initials']))->toBeGreaterThan(0)
            ->and($member['colorIndex'])->toBeInt()
            ->and($member['colorIndex'])->toBeGreaterThanOrEqual(0)
            ->and($member['colorIndex'])->toBeLessThan(6);

        if (null !== $member['avatarUrl']) {
            expect($member['avatarUrl'])->toStartWith('http');
        }
    }
});

it('GET payload includes isManager, reportIds, and topLevelTeamMemberIds', function (): void {
    // Log in as the admin (user_1) who sits ABOVE the manager (user_2 Petra).
    // Only a user above a manager should see that manager as expandable.
    $admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $this->client->loginUser($admin);

    $this->client->request('GET', '/app/api/user/calendar/customize');

    expect($this->client->getResponse()->getStatusCode())->toBe(200);
    $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($data)->toHaveKey('candidateTeamMembers')
        ->toHaveKey('topLevelTeamMemberIds');

    foreach ($data['candidateTeamMembers'] as $member) {
        expect($member)->toHaveKey('id')
            ->toHaveKey('name')
            ->toHaveKey('email')
            ->toHaveKey('isManager')
            ->toHaveKey('reportIds');
    }

    $managers = array_filter(
        $data['candidateTeamMembers'],
        fn (array $c): bool => true === $c['isManager'] && [] !== $c['reportIds'],
    );
    expect($managers)->not->toBeEmpty();
});

it('does not expose the manager as expandable to their own team member', function (): void {
    // user_3 (John Doe) is a regular member of Petra's team. Petra is his manager,
    // not his descendant, so she must NOT appear as an expandable manager.
    $member = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    $this->client->loginUser($member);

    $this->client->request('GET', '/app/api/user/calendar/customize');

    expect($this->client->getResponse()->getStatusCode())->toBe(200);
    $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

    foreach ($data['candidateTeamMembers'] as $member) {
        expect($member['isManager'])->toBeFalse()
            ->and($member['reportIds'])->toBe([]);
    }
});

it('POST without CSRF token returns 403', function (): void {
    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/calendar/customize',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode([
            'teamMemberIdsAuto' => true,
            'holidayCalendarIdsAuto' => true,
            'teamMemberIds' => [],
            'holidayCalendarIds' => [],
        ]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(403);
});

it('POST with invalid CSRF token returns 403', function (): void {
    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/calendar/customize',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        // Token too short (< 24 chars) and no Origin header — both checks null → 403
        json_encode(['_token' => 'bad', 'teamMemberIdsAuto' => true, 'holidayCalendarIdsAuto' => true]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(403);
});

it('POST auto=true persists NULL for both columns', function (): void {
    // Pre-set columns to non-null so we can confirm they become NULL
    $this->user->calendarSubscriptionTeamMemberIds = ['stale-id'];
    $this->user->calendarSubscriptionHolidayCalendarIds = ['stale-cal'];
    $this->em->flush();

    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/calendar/customize',
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => 'http://localhost',
        ],
        json_encode([
            '_token' => 'csrf-token',
            'teamMemberIdsAuto' => true,
            'holidayCalendarIdsAuto' => true,
            'teamMemberIds' => [],
            'holidayCalendarIds' => [],
        ]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $body = json_decode($this->client->getResponse()->getContent(), true);
    expect($body['success'])->toBeTrue();

    // Re-fetch from DB to assert persisted state
    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);

    expect($refreshed->calendarSubscriptionTeamMemberIds)->toBeNull();
    expect($refreshed->calendarSubscriptionHolidayCalendarIds)->toBeNull();
});

it('POST auto=false with empty arrays persists [] (not null) for both columns', function (): void {
    // Pre-set columns to null to confirm they change to empty array
    $this->user->calendarSubscriptionTeamMemberIds = null;
    $this->user->calendarSubscriptionHolidayCalendarIds = null;
    $this->em->flush();

    $this->client->loginUser($this->user);

    $this->client->request(
        'POST',
        '/app/api/user/calendar/customize',
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => 'http://localhost',
        ],
        json_encode([
            '_token' => 'csrf-token',
            'teamMemberIdsAuto' => false,
            'holidayCalendarIdsAuto' => false,
            'teamMemberIds' => [],
            'holidayCalendarIds' => [],
        ]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $body = json_decode($this->client->getResponse()->getContent(), true);
    expect($body['success'])->toBeTrue();

    // Re-fetch from DB — must be empty array, NOT null
    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);

    expect($refreshed->calendarSubscriptionTeamMemberIds)->toBe([]);
    expect($refreshed->calendarSubscriptionHolidayCalendarIds)->toBe([]);
});

it('POST auto=false with a valid candidate team member id persists that id', function (): void {
    // First GET to discover a real candidate id
    $this->client->loginUser($this->user);
    $this->client->request('GET', '/app/api/user/calendar/customize');

    $data = json_decode($this->client->getResponse()->getContent(), true);
    $firstMemberId = $data['candidateTeamMembers'][0]['id'];

    // Re-create client and re-login (previous request consumed the login)
    $this->client = static::createClient();
    $this->em->clear();
    $freshUser = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);
    $this->client->loginUser($freshUser);

    $this->client->request(
        'POST',
        '/app/api/user/calendar/customize',
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ORIGIN' => 'http://localhost',
        ],
        json_encode([
            '_token' => 'csrf-token',
            'teamMemberIdsAuto' => false,
            'holidayCalendarIdsAuto' => false,
            'teamMemberIds' => [$firstMemberId],
            'holidayCalendarIds' => [],
        ]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    // Re-fetch from DB — the valid id must have been kept by the allowlist filter
    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);

    expect($refreshed->calendarSubscriptionTeamMemberIds)->toBe([$firstMemberId]);
    expect($refreshed->calendarSubscriptionHolidayCalendarIds)->toBe([]);
});

it('GET as manager returns the two direct reports in myTeamMemberIds, all also top-level', function (): void {
    $this->client->loginUser($this->user);

    $this->client->request('GET', '/app/api/user/calendar/customize');

    expect($this->client->getResponse()->getStatusCode())->toBe(200);
    $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($data)->toHaveKey('myTeamMemberIds');
    expect($data['myTeamMemberIds'])->toHaveCount(2);

    foreach ($data['myTeamMemberIds'] as $id) {
        expect($data['topLevelTeamMemberIds'])->toContain($id);
    }
});

it('GET as admin includes the sub-manager Petra in myTeamMemberIds with isManager and reportIds', function (): void {
    $admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $this->client->loginUser($admin);

    $this->client->request('GET', '/app/api/user/calendar/customize');

    expect($this->client->getResponse()->getStatusCode())->toBe(200);
    $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

    $petra = null;
    foreach ($data['candidateTeamMembers'] as $member) {
        if ('manager@whoisooo.app' === $member['email']) {
            $petra = $member;
            break;
        }
    }

    expect($petra)->not->toBeNull()
        ->and($data['myTeamMemberIds'])->toContain($petra['id'])
        ->and($petra['isManager'])->toBeTrue()
        ->and($petra['reportIds'])->not->toBeEmpty();
});

it('GET as a plain member returns an empty myTeamMemberIds', function (): void {
    $member = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    $this->client->loginUser($member);

    $this->client->request('GET', '/app/api/user/calendar/customize');

    expect($this->client->getResponse()->getStatusCode())->toBe(200);
    $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($data)->toHaveKey('myTeamMemberIds');
    expect($data['myTeamMemberIds'])->toBe([]);
});

it('POST as admin persists a sub-manager report id (including a manager\'s team member)', function (): void {
    // Hans (admin) sits above Petra (a manager). Petra's own reports are descendants of Hans,
    // so they are valid candidates Hans may opt into his feed. Verify one persists end-to-end.
    $admin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $this->client->loginUser($admin);
    $this->client->request('GET', '/app/api/user/calendar/customize');
    $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

    $petra = null;
    foreach ($data['candidateTeamMembers'] as $candidate) {
        if ('manager@whoisooo.app' === $candidate['email']) {
            $petra = $candidate;
            break;
        }
    }
    expect($petra)->not->toBeNull()
        ->and($petra['reportIds'])->not->toBeEmpty();

    $subReportId = $petra['reportIds'][0];
    // The sub-report is a legitimate candidate for the admin (it is a management descendant).
    expect(array_column($data['candidateTeamMembers'], 'id'))->toContain($subReportId);

    $this->client = static::createClient();
    $this->em->clear();
    $freshAdmin = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    $this->client->loginUser($freshAdmin);

    $this->client->request(
        'POST',
        '/app/api/user/calendar/customize',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'],
        json_encode([
            '_token' => 'csrf-token',
            'teamMemberIdsAuto' => false,
            'holidayCalendarIdsAuto' => true,
            'teamMemberIds' => [$subReportId],
            'holidayCalendarIds' => [],
        ]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'admin@whoisooo.app']);
    expect($refreshed->calendarSubscriptionTeamMemberIds)->toBe([$subReportId]);
});

it('POST as manager persists exactly their two own report ids', function (): void {
    $this->client->loginUser($this->user);
    $this->client->request('GET', '/app/api/user/calendar/customize');
    $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

    $reportIds = $data['myTeamMemberIds'];
    expect($reportIds)->toHaveCount(2);

    $this->client = static::createClient();
    $this->em->clear();
    $freshManager = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);
    $this->client->loginUser($freshManager);

    $this->client->request(
        'POST',
        '/app/api/user/calendar/customize',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'],
        json_encode([
            '_token' => 'csrf-token',
            'teamMemberIdsAuto' => false,
            'holidayCalendarIdsAuto' => true,
            'teamMemberIds' => $reportIds,
            'holidayCalendarIds' => [],
        ]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);

    $stored = $refreshed->calendarSubscriptionTeamMemberIds;
    sort($stored);
    sort($reportIds);
    expect($stored)->toBe($reportIds);
});

it('POST as a regular member drops team ids outside their candidate set (server-side allowlist)', function (): void {
    // A plain member must not be able to store ids the server never offered them — even by
    // crafting the POST directly. The allowlist keeps the legitimate id and drops the rogue one.
    $member = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    $this->client->loginUser($member);
    $this->client->request('GET', '/app/api/user/calendar/customize');
    $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($data['candidateTeamMembers'])->not->toBeEmpty();
    $validId = $data['candidateTeamMembers'][0]['id'];

    $unauthorizedId = '00000000-0000-4000-8000-000000000000';
    expect(array_column($data['candidateTeamMembers'], 'id'))->not->toContain($unauthorizedId);

    $this->client = static::createClient();
    $this->em->clear();
    $freshMember = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    $this->client->loginUser($freshMember);

    $this->client->request(
        'POST',
        '/app/api/user/calendar/customize',
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'],
        json_encode([
            '_token' => 'csrf-token',
            'teamMemberIdsAuto' => false,
            'holidayCalendarIdsAuto' => true,
            'teamMemberIds' => [$validId, $unauthorizedId],
            'holidayCalendarIds' => [],
        ]),
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
    expect($refreshed->calendarSubscriptionTeamMemberIds)->toBe([$validId]);
});
