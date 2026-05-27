<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;

/*
 * Functional tests for GET+POST /app/api/user/calendar/customize
 *
 * Fixture setup (from src/DataFixtures/fixtures.yaml):
 *   - user_2 (Petra Schmidt, manager@whoisooo.app): ROLE_MANAGER, holiday_calendar_de
 *     Teammates (have manager=user_2): user_3 (John Doe), user_4 (Sofia Bergström),
 *       user_5 (Marco Rossi), user_6 (Aisha Patel)
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
        ->toHaveKey('selectedTeamMemberIds')
        ->toHaveKey('selectedHolidayCalendarIds');

    expect($data['candidateTeamMembers'])->not->toBeEmpty();

    foreach ($data['candidateTeamMembers'] as $member) {
        expect($member)->toHaveKey('id')
            ->toHaveKey('name')
            ->toHaveKey('email');
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
