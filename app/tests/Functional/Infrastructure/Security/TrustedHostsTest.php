<?php

declare(strict_types=1);

it('rejects requests for a host that is not trusted', function (): void {
    $client = static::createClient();
    $client->request('GET', 'http://evil.tld/password-reset/request');

    expect($client->getResponse()->getStatusCode())->toBe(400);
});

it('serves requests for a trusted host', function (): void {
    $client = static::createClient();
    $client->request('GET', 'http://localhost/password-reset/request');

    expect($client->getResponse()->getStatusCode())->toBe(200);
});
