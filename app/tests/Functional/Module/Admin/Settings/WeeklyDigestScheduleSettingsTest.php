<?php

declare(strict_types=1);

it('renders the weekly digest schedule settings for an admin', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    $client->request('GET', '/app/settings');
    $crawler = $client->getCrawler();

    expect($crawler->text())
        ->toContain('Weekly Digest Schedule')
        ->toContain('Send day')
        ->toContain('Send time')
        ->toContain('Timezone');

    expect($crawler->filter('select[name="app_settings_form[weeklyDigestDay]"]')->count())->toBe(1)
        ->and($crawler->filter('select[name="app_settings_form[weeklyDigestTimezone]"]')->count())->toBe(1);
});
