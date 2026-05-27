<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;

/*
 * Functional tests for POST /app/api/user/calendar/regenerate
 *
 * The controller reads _token from the POST body (FormData), not JSON.
 *
 * Stateless CSRF pattern (SameOriginCsrfTokenManager):
 *   - Sending HTTP_ORIGIN: http://localhost makes isValidOrigin() return true.
 *   - Token value 'csrf-token' (== cookieName) bypasses the double-submit length check.
 *   - Together they satisfy isCsrfTokenValid() without a session or cookie.
 *
 * NOTE: this is the server-side contract. The real browser path (JS token rotation
 * + double-submit cookie) is covered by RegenerateCalendarSubscriptionBrowserTest.
 */
beforeEach(function (): void {
    $this->client = static::createClient();
    $this->em = static::getContainer()->get('doctrine')->getManager();

    $this->user = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);
});

it('POST without CSRF token returns 403', function (): void {
    $this->client->loginUser($this->user);

    $this->client->request('POST', '/app/api/user/calendar/regenerate');

    expect($this->client->getResponse()->getStatusCode())->toBe(403);
});

it('POST with invalid CSRF token returns 403', function (): void {
    $this->client->loginUser($this->user);

    // Token too short (< 24 chars) and no Origin header — both checks null → 403
    $this->client->request('POST', '/app/api/user/calendar/regenerate', ['_token' => 'bad']);

    expect($this->client->getResponse()->getStatusCode())->toBe(403);
});

it('POST with valid token and origin regenerates the ical salt and returns a new url', function (): void {
    $this->client->loginUser($this->user);

    $saltBefore = $this->user->icalHashSalt;

    $this->client->request(
        'POST',
        '/app/api/user/calendar/regenerate',
        ['_token' => 'csrf-token'],
        [],
        ['HTTP_ORIGIN' => 'http://localhost'],
    );

    expect($this->client->getResponse()->getStatusCode())->toBe(200);

    $body = json_decode($this->client->getResponse()->getContent(), true);
    expect($body['success'])->toBeTrue()
        ->and($body['url'])->toBeString()
        ->and($body['url'])->not->toBeEmpty();

    // Re-fetch from DB — the salt must have rotated to a fresh 32-char hex value
    $this->em->clear();
    $refreshed = $this->em->getRepository(User::class)->findOneBy(['email' => 'user@whoisooo.app']);

    expect($refreshed->icalHashSalt)->not->toBe($saltBefore)
        ->and(strlen((string) $refreshed->icalHashSalt))->toBe(32)
        ->and(ctype_xdigit((string) $refreshed->icalHashSalt))->toBeTrue();
});
