<?php

declare(strict_types=1);

use App\Infrastructure\WhoIsOoo\HomepageUrlProvider;

it('returns url via getUrl() for valid url', function (): void {
    $provider = new HomepageUrlProvider('https://whoisooo.app');

    expect($provider->getUrl())->toBe('https://whoisooo.app');
});

it('returns url via __toString() for valid url', function (): void {
    $provider = new HomepageUrlProvider('https://whoisooo.app');

    expect((string) $provider)->toBe('https://whoisooo.app');
});

it('trims trailing whitespace from url', function (): void {
    $provider = new HomepageUrlProvider('https://whoisooo.app   ');

    expect($provider->getUrl())->toBe('https://whoisooo.app')
        ->and((string) $provider)->toBe('https://whoisooo.app');
});

it('trims leading whitespace from url', function (): void {
    $provider = new HomepageUrlProvider('   https://whoisooo.app');

    expect($provider->getUrl())->toBe('https://whoisooo.app');
});

it('returns empty string for empty input', function (): void {
    $provider = new HomepageUrlProvider('');

    expect($provider->getUrl())->toBe('')
        ->and((string) $provider)->toBe('');
});

it('returns empty string for whitespace-only input', function (): void {
    $provider = new HomepageUrlProvider('   ');

    expect($provider->getUrl())->toBe('');
});

it('accepts http:// URL', function (): void {
    $provider = new HomepageUrlProvider('http://whoisooo.app');

    expect($provider->getUrl())->toBe('http://whoisooo.app');
});

it('accepts URL with no scheme', function (): void {
    $provider = new HomepageUrlProvider('whoisooo.app');

    expect($provider->getUrl())->toBe('whoisooo.app');
});

it('accepts uppercase HTTPS scheme', function (): void {
    $provider = new HomepageUrlProvider('HTTPS://whoisooo.app');

    expect($provider->getUrl())->toBe('HTTPS://whoisooo.app');
});

it('appends UTM params via getSidebarUrl() on a bare URL', function (): void {
    $provider = new HomepageUrlProvider('https://whoisooo.app');

    expect($provider->getSidebarUrl())->toBe('https://whoisooo.app?utm_source=app&utm_medium=sidebar');
});

it('appends UTM params on URL with path', function (): void {
    $provider = new HomepageUrlProvider('https://whoisooo.app/landing');

    expect($provider->getSidebarUrl())->toBe('https://whoisooo.app/landing?utm_source=app&utm_medium=sidebar');
});

it('merges UTM params with existing query string', function (): void {
    $provider = new HomepageUrlProvider('https://whoisooo.app/?ref=internal');

    $result = $provider->getSidebarUrl();

    expect($result)->toContain('ref=internal')
        ->and($result)->toContain('utm_source=app')
        ->and($result)->toContain('utm_medium=sidebar')
        ->and(substr_count($result, '?'))->toBe(1);
});

it('overrides existing UTM params on conflict', function (): void {
    $provider = new HomepageUrlProvider('https://whoisooo.app/?utm_source=other');

    $result = $provider->getSidebarUrl();

    expect($result)->toContain('utm_source=app')
        ->and($result)->not->toContain('utm_source=other');
});

it('preserves fragment when appending UTM params', function (): void {
    $provider = new HomepageUrlProvider('https://whoisooo.app/#features');

    expect($provider->getSidebarUrl())->toBe('https://whoisooo.app/?utm_source=app&utm_medium=sidebar#features');
});

it('preserves port when appending UTM params', function (): void {
    $provider = new HomepageUrlProvider('http://whoisooo.app:8080/path');

    expect($provider->getSidebarUrl())->toBe('http://whoisooo.app:8080/path?utm_source=app&utm_medium=sidebar');
});

it('returns empty string from getSidebarUrl() when url is empty', function (): void {
    $provider = new HomepageUrlProvider('');

    expect($provider->getSidebarUrl())->toBe('');
});

it('withQuery() returns the url unchanged when params are empty', function (): void {
    $provider = new HomepageUrlProvider('https://whoisooo.app/?a=1');

    expect($provider->withQuery([]))->toBe('https://whoisooo.app/?a=1');
});
