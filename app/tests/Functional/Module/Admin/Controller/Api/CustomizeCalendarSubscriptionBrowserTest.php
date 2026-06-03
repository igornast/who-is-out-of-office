<?php

declare(strict_types=1);

/*
 * Real-browser test for the redesigned "Customize calendar subscription" modal.
 *
 * Logs in as manager@whoisooo.app (Petra Schmidt), whose two direct reports render as
 * a "My team" group with a master select-all checkbox. Drives the Option A redesign:
 *   - avatars (img or initials placeholder) on team rows,
 *   - the per-section Auto / Custom segmented toggle + its auto note,
 *   - the master <-> reports checkbox (with indeterminate "partial" state),
 *   - collapse-safety: a selection made before collapsing the group still persists on save
 *     (verified by reopening the modal from a fresh page load and re-reading the checkboxes).
 *
 * Fixture wiring (src/DataFixtures/fixtures.yaml): user_2 (Petra, manager@whoisooo.app,
 * ROLE_MANAGER) directly manages user_12 (Elena Novak) and user_13 (David Kim) —
 * exactly two direct reports, both ROLE_MANAGER sub-managers.
 */
beforeEach(function (): void {
    static::bootKernel();
});

it('renders avatars and a working Auto/Custom toggle with the auto note', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'manager@whoisooo.app', '123');

    $client->request('GET', '/app/user/profile');
    $client->waitFor('button[data-bs-target="#calendarCustomizeModal"]');

    // Open via JS to dodge Bootstrap fade-animation click interception.
    $client->executeScript("document.querySelector('button[data-bs-target=\"#calendarCustomizeModal\"]').click();");
    $client->waitForVisibility('#calendarCustomizeSections');

    // Avatars rendered (image or initials placeholder) for at least one team row.
    $avatarCount = (int) $client->executeScript(
        "return document.querySelectorAll('#calendarCustomizeTeamList .avatar-img, #calendarCustomizeTeamList .avatar-placeholder').length;"
    );
    expect($avatarCount)->toBeGreaterThan(0);

    // The team section has a segmented Auto / Custom toggle.
    $hasToggle = (bool) $client->executeScript(
        "return !!document.querySelector('.cc-seg button[data-section=\"team\"][data-mode=\"auto\"]') "
        ."&& !!document.querySelector('.cc-seg button[data-section=\"team\"][data-mode=\"custom\"]');"
    );
    expect($hasToggle)->toBeTrue();

    // Switching to Custom hides the auto note and marks the Custom button active.
    $client->executeScript("document.querySelector('.cc-seg button[data-section=\"team\"][data-mode=\"custom\"]').click();");
    $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
        "const n = document.getElementById('calendarCustomizeTeamNote'); return !!n && n.offsetParent === null;"
    ));
    $customOn = (bool) $client->executeScript(
        "return document.querySelector('.cc-seg button[data-section=\"team\"][data-mode=\"custom\"]').classList.contains('is-on');"
    );
    expect($customOn)->toBeTrue();

    // Switching back to Auto shows the auto note again and marks the Auto button active.
    $client->executeScript("document.querySelector('.cc-seg button[data-section=\"team\"][data-mode=\"auto\"]').click();");
    $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
        "const n = document.getElementById('calendarCustomizeTeamNote'); return !!n && n.offsetParent !== null;"
    ));
    $autoOn = (bool) $client->executeScript(
        "return document.querySelector('.cc-seg button[data-section=\"team\"][data-mode=\"auto\"]').classList.contains('is-on');"
    );
    expect($autoOn)->toBeTrue();
});

it('drives the My team master checkbox with indeterminate state, then collapses and saves persistently', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'manager@whoisooo.app', '123');

    $client->request('GET', '/app/user/profile');
    $client->waitFor('button[data-bs-target="#calendarCustomizeModal"]');
    $client->executeScript("document.querySelector('button[data-bs-target=\"#calendarCustomizeModal\"]').click();");
    $client->waitForVisibility('#calendarCustomizeSections');

    // Petra's two direct reports render as a "My team" group with a master checkbox.
    $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
        "return !!document.querySelector('[data-action=\"toggle-my-team-all\"]');"
    ));
    $childCount = (int) $client->executeScript(
        "return document.querySelectorAll('#calendarCustomizeMyTeamChildren input[data-action=\"toggle-member\"]').length;"
    );
    expect($childCount)->toBe(2);

    // Master forces every report checkbox on; master is then checked and not indeterminate.
    $client->executeScript(
        "const m = document.querySelector('[data-action=\"toggle-my-team-all\"]'); if (!m.checked) { m.click(); }"
    );
    $client->wait(5)->until(static fn (): bool => (int) $client->executeScript(
        "return document.querySelectorAll('#calendarCustomizeMyTeamChildren input[data-action=\"toggle-member\"]:checked').length;"
    ) === $childCount);
    expect((bool) $client->executeScript("return document.querySelector('[data-action=\"toggle-my-team-all\"]').checked;"))->toBeTrue()
        ->and((bool) $client->executeScript("return document.querySelector('[data-action=\"toggle-my-team-all\"]').indeterminate;"))->toBeFalse();

    // Unchecking ONE report puts the master into the indeterminate "partial" state.
    $uncheckedId = (string) $client->executeScript(
        "const c = document.querySelector('#calendarCustomizeMyTeamChildren input[data-action=\"toggle-member\"]'); c.click(); return c.getAttribute('data-id');"
    );
    $client->wait(5)->until(static fn (): bool => true === $client->executeScript(
        "return document.querySelector('[data-action=\"toggle-my-team-all\"]').indeterminate;"
    ));
    expect((bool) $client->executeScript("return document.querySelector('[data-action=\"toggle-my-team-all\"]').indeterminate;"))->toBeTrue();

    // Editing a checkbox flips the team section to Custom.
    expect((bool) $client->executeScript(
        "return document.querySelector('.cc-seg button[data-section=\"team\"][data-mode=\"custom\"]').classList.contains('is-on');"
    ))->toBeTrue();

    // Collapse the group (children leave the DOM / are hidden), then save.
    $client->executeScript("document.querySelector('[data-action=\"toggle-my-team-expand\"]').click();");
    $client->executeScript("document.getElementById('calendarCustomizeSaveBtn').click();");
    $client->wait(10)->until(static fn (): bool => (bool) $client->executeScript(
        "return !document.getElementById('calendarCustomizeSuccess').classList.contains('d-none');"
    ));

    // Reopen from a fresh page load: one report persisted checked, the unchecked one did not.
    $client->request('GET', '/app/user/profile');
    $client->waitFor('button[data-bs-target="#calendarCustomizeModal"]');
    $client->executeScript("document.querySelector('button[data-bs-target=\"#calendarCustomizeModal\"]').click();");
    $client->waitForVisibility('#calendarCustomizeSections');
    $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
        "return !!document.querySelector('#calendarCustomizeMyTeamChildren input[data-action=\"toggle-member\"]');"
    ));

    $checkedAfter = (int) $client->executeScript(
        "return document.querySelectorAll('#calendarCustomizeMyTeamChildren input[data-action=\"toggle-member\"]:checked').length;"
    );
    expect($checkedAfter)->toBe(1);

    $uncheckedStaysUnchecked = (bool) $client->executeScript(sprintf(
        "const c = document.querySelector('#calendarCustomizeMyTeamChildren input[data-id=\"%s\"]'); return !!c && !c.checked;",
        $uncheckedId,
    ));
    expect($uncheckedStaysUnchecked)->toBeTrue();
});

it('lets an admin expand a sub-manager inside My team and include that sub-team, persisted across reload', function (): void {
    // Hans Mueller (admin@whoisooo.app) directly manages two sub-managers — Petra (user_2)
    // and Robert (user_11) — so the My team group exposes two expandable rows. The first by
    // name order (Kwame, Mei, Petra, Robert) is Petra, who herself manages two sub-managers
    // (Elena, David), so expanding her reveals a two-member tray with an include-all master.
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    $client->request('GET', '/app/user/profile');
    $client->waitFor('button[data-bs-target="#calendarCustomizeModal"]');
    $client->executeScript("document.querySelector('button[data-bs-target=\"#calendarCustomizeModal\"]').click();");
    $client->waitForVisibility('#calendarCustomizeSections');

    // Two sub-managers (Petra and Robert) are expandable inside the My team group.
    $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
        "return !!document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-expand\"]');"
    ));
    $expandableCount = (int) $client->executeScript(
        "return document.querySelectorAll('#calendarCustomizeMyTeamChildren [data-action=\"toggle-expand\"]').length;"
    );
    expect($expandableCount)->toBe(2);

    // Expand the first sub-manager (Petra) -> her direct reports appear in a tray with an include-all master.
    $client->executeScript("document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-expand\"]').click();");
    $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
        "return !!document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-include-all\"]');"
    ));

    $subReportTotal = (int) $client->executeScript(
        "const m = document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-include-all\"]');"
        ."return m.closest('.cc-kids').querySelectorAll('input[data-action=\"toggle-member\"]').length;"
    );
    expect($subReportTotal)->toBe(2);

    // Auto = your own team only, so the sub-team (grandchildren) starts unselected.
    $subCheckedBefore = (int) $client->executeScript(
        "const m = document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-include-all\"]');"
        ."return m.closest('.cc-kids').querySelectorAll('input[data-action=\"toggle-member\"]:checked').length;"
    );
    expect($subCheckedBefore)->toBe(0);

    // Include the whole sub-team via the master.
    $client->executeScript(
        "const m = document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-include-all\"]'); if (!m.checked) { m.click(); }"
    );
    $client->wait(5)->until(static fn (): bool => (int) $client->executeScript(
        "const m = document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-include-all\"]');"
        ."return m.closest('.cc-kids').querySelectorAll('input[data-action=\"toggle-member\"]:checked').length;"
    ) === $subReportTotal);

    $client->executeScript("document.getElementById('calendarCustomizeSaveBtn').click();");
    $client->wait(10)->until(static fn (): bool => (bool) $client->executeScript(
        "return !document.getElementById('calendarCustomizeSuccess').classList.contains('d-none');"
    ));

    // Reopen from a fresh load and re-expand: the sub-team selection persisted.
    $client->request('GET', '/app/user/profile');
    $client->waitFor('button[data-bs-target="#calendarCustomizeModal"]');
    $client->executeScript("document.querySelector('button[data-bs-target=\"#calendarCustomizeModal\"]').click();");
    $client->waitForVisibility('#calendarCustomizeSections');
    $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
        "return !!document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-expand\"]');"
    ));
    $client->executeScript("document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-expand\"]').click();");
    $client->wait(5)->until(static fn (): bool => (bool) $client->executeScript(
        "return !!document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-include-all\"]');"
    ));

    $subCheckedAfter = (int) $client->executeScript(
        "const m = document.querySelector('#calendarCustomizeMyTeamChildren [data-action=\"toggle-include-all\"]');"
        ."return m.closest('.cc-kids').querySelectorAll('input[data-action=\"toggle-member\"]:checked').length;"
    );
    expect($subCheckedAfter)->toBe(2);
});

it('does not expose any expandable sub-manager to a regular team member', function (): void {
    // John Doe (user@whoisooo.app) is a plain member of Petra's team: he must never be able to
    // drill into anyone's reports (the server never marks his candidates as managers).
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@whoisooo.app', '123');

    $client->request('GET', '/app/user/profile');
    $client->waitFor('button[data-bs-target="#calendarCustomizeModal"]');
    $client->executeScript("document.querySelector('button[data-bs-target=\"#calendarCustomizeModal\"]').click();");
    $client->waitForVisibility('#calendarCustomizeSections');

    $expandable = (int) $client->executeScript(
        "return document.querySelectorAll('#calendarCustomizeTeamList [data-action=\"toggle-expand\"]').length;"
    );
    expect($expandable)->toBe(0);
});
