<?php

declare(strict_types=1);

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

beforeEach(function (): void {
    static::bootKernel();
    $this->router = static::getContainer()->get('router');
});

it('builds absolute invitation URLs from the configured base URL when there is no request', function (): void {
    $url = $this->router->generate(
        'app_user_invitation',
        ['token' => 'some-token'],
        UrlGeneratorInterface::ABSOLUTE_URL,
    );

    expect($url)->toBe('https://whoisooo.test/invitation/some-token');
});

it('never falls back to localhost for absolute URLs', function (): void {
    $url = $this->router->generate(
        'app_dashboard',
        [],
        UrlGeneratorInterface::ABSOLUTE_URL,
    );

    expect($url)->toStartWith('https://whoisooo.test/')
        ->and($url)->not->toContain('localhost');
});
