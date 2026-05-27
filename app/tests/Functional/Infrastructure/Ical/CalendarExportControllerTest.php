<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\DTO\UserDTO;
use App\Shared\Service\Ical\IcalSubscriptionUrlGenerator;

/*
 * Functional test for GET /api/calendar/{userId}/{secret}.ics
 *
 * Fixture setup (from src/DataFixtures/fixtures.yaml):
 *   - user_2 (Petra Schmidt, manager@whoisooo.app): manager=user_1, holiday_calendar_de
 *     Own approved leave: leave_request_1 (+2..+6 days), leave_request_2 (+8..+9 days)
 *     Teammates (share manager user_2): user_3 (John Doe), user_4 (Sofia Bergström),
 *       user_5 (Marco Rossi), user_6 (Aisha Patel) — all have approved leave in-window
 *   - holiday_calendar_de has holiday_4 (Tag der Deutschen Einheit, 2026-10-03) — in +1 year window
 */
beforeEach(function (): void {
    $this->client = static::createClient();
    $this->em = static::getContainer()->get('doctrine')->getManager();
    $this->urlGenerator = static::getContainer()->get(IcalSubscriptionUrlGenerator::class);

    $this->manager = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);
});

it('returns 403 for invalid secret', function (): void {
    $userId = $this->manager->id->toString();

    $this->client->request('GET', sprintf('/api/calendar/%s/badsecret.ics', $userId));

    expect($this->client->getResponse()->getStatusCode())->toBe(403);
});

it('auto config (null/null) returns valid calendar with own leave and teammate leave', function (): void {
    // Ensure subscription config is null/null (auto mode)
    $this->manager->calendarSubscriptionTeamMemberIds = null;
    $this->manager->calendarSubscriptionHolidayCalendarIds = null;
    $this->em->flush();

    $userDTO = UserDTO::fromEntity($this->manager);
    $url = $this->urlGenerator->generateForUser($userDTO);
    $path = parse_url($url, PHP_URL_PATH);

    $this->client->request('GET', $path);
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(200);

    $contentType = $response->headers->get('Content-Type');
    expect($contentType)->toContain('text/calendar');

    $body = $response->getContent();
    expect($body)->toContain('BEGIN:VCALENDAR');

    // Own leave: Petra Schmidt appears in own leave events
    expect($body)->toContain('Petra');
    expect($body)->toContain('Schmidt');

    // Teammate leave: Sofia Bergström (user_4) has leave_request_3 (+1..+3 days) in-window
    expect($body)->toContain('Sofia');
});

it('explicit empty opt-out ([]/[]) keeps own leave but excludes teammates and holidays', function (): void {
    // Opt out of all teammates and all holiday calendars
    $this->manager->calendarSubscriptionTeamMemberIds = [];
    $this->manager->calendarSubscriptionHolidayCalendarIds = [];
    $this->em->flush();
    $this->em->clear();

    $manager = $this->em->getRepository(User::class)->findOneBy(['email' => 'manager@whoisooo.app']);
    $userDTO = UserDTO::fromEntity($manager);
    $url = $this->urlGenerator->generateForUser($userDTO);
    $path = parse_url($url, PHP_URL_PATH);

    $this->client->request('GET', $path);
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(200);
    $body = $response->getContent();

    expect($body)->toContain('BEGIN:VCALENDAR');

    // Own leave must still be present
    expect($body)->toContain('Petra');
    expect($body)->toContain('Schmidt');

    // No holiday events — neither the DE nor FR calendar holidays should appear
    expect($body)->not->toContain('Tag der Deutschen Einheit');
    expect($body)->not->toContain('Bastille Day');

    // Teammate Sofia Bergström must NOT appear
    expect($body)->not->toContain('Sofia');
});

it('auto config (null/null) includes holiday events for user holiday calendar country', function (): void {
    // holiday_calendar_de has holiday_4: Tag der Deutschen Einheit on YEAR-10-03
    // Today is 2026-05-24; window is -1 month .. +1 year => 2026-04-24 .. 2027-05-24
    // 2026-10-03 is within the window — so this holiday WILL appear
    $this->manager->calendarSubscriptionTeamMemberIds = null;
    $this->manager->calendarSubscriptionHolidayCalendarIds = null;
    $this->em->flush();

    $userDTO = UserDTO::fromEntity($this->manager);
    $url = $this->urlGenerator->generateForUser($userDTO);
    $path = parse_url($url, PHP_URL_PATH);

    $this->client->request('GET', $path);
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(200);
    $body = $response->getContent();

    // Holiday events are prefixed with the country flag emoji (🇩🇪 for DE) in SUMMARY
    expect($body)->toContain('Tag der Deutschen Einheit');
});

it('auto config (null/null) includes teammate-country holiday calendars', function (): void {
    // user_2 (Petra, DE calendar) has teammates user_3/4/6 on holiday_calendar_fr.
    // holiday_calendar_fr has holiday_1 (Bastille Day, +15 days from today) — firmly in window.
    // Under the old buggy behavior, findTeammatesOf returned null calendarCountryCode for every
    // teammate, so FR was never added to the candidate set and Bastille Day never appeared.
    // After Fix 1 (LEFT JOIN holiday_calendar in findTeammatesOf + fromArray reading
    // calendar_country_code), FR surfaces as a candidate country and its holidays are included.
    $this->manager->calendarSubscriptionTeamMemberIds = null;
    $this->manager->calendarSubscriptionHolidayCalendarIds = null;
    $this->em->flush();

    $userDTO = UserDTO::fromEntity($this->manager);
    $url = $this->urlGenerator->generateForUser($userDTO);
    $path = parse_url($url, PHP_URL_PATH);

    $this->client->request('GET', $path);
    $response = $this->client->getResponse();

    expect($response->getStatusCode())->toBe(200);
    $body = $response->getContent();

    // Bastille Day is a FR holiday assigned to teammates (user_3, user_4, user_6).
    // This assertion would FAIL under the pre-fix code path (own-country DE only).
    expect($body)->toContain('Bastille Day');
});
