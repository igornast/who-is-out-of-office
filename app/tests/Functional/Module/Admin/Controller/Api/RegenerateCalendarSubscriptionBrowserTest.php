<?php

declare(strict_types=1);

/*
 * Real-browser test for the "Regenerate calendar URL" modal on the profile page.
 *
 * Unlike the kernel-client controller test (which hardcodes the placeholder token +
 * Origin and rides the origin-only CSRF fallback), this drives the actual JS:
 *   - logs in via the real login form (so the session is a genuine double-submit one),
 *   - opens the confirm modal and confirms,
 *   - letting window.AppCsrf.rotateStatelessCsrfToken() rotate the token and set the
 *     __Host-/SameSite double-submit cookie before the fetch.
 *
 * Success is observed as the readonly subscription URL input swapping to a new value,
 * which only happens when the POST returns 200 — i.e. CSRF validation passed end-to-end.
 */
beforeEach(function (): void {
    static::bootKernel();
});

it('regenerates the calendar subscription URL through the modal', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@whoisooo.app', '123');

    $client->request('GET', '/app/user/profile');
    $client->waitFor('#profile-regenerate-cal-url');

    $urlBefore = $client->executeScript("return document.getElementById('profile-cal-url').value;");

    // Click via JS to avoid Bootstrap modal fade-animation click interception.
    $client->executeScript("document.getElementById('profile-regenerate-cal-url').click();");
    $client->waitForVisibility('#regenerateConfirmBtn');
    $client->executeScript("document.getElementById('regenerateConfirmBtn').click();");

    // On a 200 the JS swaps #profile-cal-url to the new URL; wait until it changes.
    $client->wait(10)->until(static fn (): bool => $client->executeScript(
        "return document.getElementById('profile-cal-url').value;"
    ) !== $urlBefore);

    $urlAfter = $client->executeScript("return document.getElementById('profile-cal-url').value;");

    expect($urlAfter)->not->toBe($urlBefore)
        ->and($urlAfter)->not->toBeEmpty();
});
