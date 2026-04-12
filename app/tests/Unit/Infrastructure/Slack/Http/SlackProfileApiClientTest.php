<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Exception\SlackStatusApiException;
use App\Infrastructure\Slack\Http\SlackProfileApiClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

it('POSTs users.profile.set with the expected payload on setProfileStatus', function (): void {
    $received = [];
    $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$received) {
        $received = ['method' => $method, 'url' => $url, 'body' => $options['body'], 'headers' => $options['headers']];

        return new MockResponse(json_encode(['ok' => true]));
    });

    $client = new SlackProfileApiClient($http);
    $client->setProfileStatus('xoxp-test', 'U123', 'Vacation until Mar 24', ':palm_tree:', 1742860799);

    expect($received['method'])->toBe('POST')
        ->and($received['url'])->toBe('https://slack.com/api/users.profile.set')
        ->and($received['headers'])->toContain('Authorization: Bearer xoxp-test')
        ->and($received['headers'])->toContain('Content-Type: application/json; charset=utf-8');

    $decoded = json_decode($received['body'], true);
    expect($decoded['user'])->toBe('U123')
        ->and($decoded['profile']['status_text'])->toBe('Vacation until Mar 24')
        ->and($decoded['profile']['status_emoji'])->toBe(':palm_tree:')
        ->and($decoded['profile']['status_expiration'])->toBe(1742860799);
});

it('POSTs empty values on clearProfileStatus', function (): void {
    $received = [];
    $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$received) {
        $received = ['body' => $options['body']];

        return new MockResponse(json_encode(['ok' => true]));
    });

    $client = new SlackProfileApiClient($http);
    $client->clearProfileStatus('xoxp-test', 'U123');

    $decoded = json_decode($received['body'], true);
    expect($decoded['profile']['status_text'])->toBe('')
        ->and($decoded['profile']['status_emoji'])->toBe('')
        ->and($decoded['profile']['status_expiration'])->toBe(0);
});

it('throws SlackStatusApiException when Slack returns ok=false', function (): void {
    $http = new MockHttpClient(fn () => new MockResponse(json_encode(['ok' => false, 'error' => 'invalid_auth'])));
    $client = new SlackProfileApiClient($http);

    try {
        $client->setProfileStatus('xoxp-bad', 'U123', 'Vacation', ':calendar:', 1742860799);
        test()->fail('Expected SlackStatusApiException');
    } catch (SlackStatusApiException $e) {
        expect($e->slackError)->toBe('invalid_auth')
            ->and($e->isFatalAuthError())->toBeTrue();
    }
});

it('exchanges an OAuth code', function (): void {
    $received = [];
    $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$received) {
        $received = ['url' => $url, 'body' => $options['body']];

        return new MockResponse(json_encode([
            'ok' => true,
            'authed_user' => ['id' => 'U42', 'access_token' => 'xoxp-new-token'],
        ]));
    });

    $client = new SlackProfileApiClient($http);
    $result = $client->exchangeOauthCode('the-code', 'https://example.test/callback', 'client-id', 'client-secret');

    expect($received['url'])->toBe('https://slack.com/api/oauth.v2.access')
        ->and($received['body'])->toContain('client_id=client-id')
        ->and($received['body'])->toContain('client_secret=client-secret')
        ->and($received['body'])->toContain('code=the-code')
        ->and($result['access_token'])->toBe('xoxp-new-token')
        ->and($result['user_id'])->toBe('U42');
});

it('throws when oauth response has ok=false', function (): void {
    $http = new MockHttpClient(fn () => new MockResponse(json_encode(['ok' => false, 'error' => 'invalid_code'])));
    $client = new SlackProfileApiClient($http);

    $client->exchangeOauthCode('bad', 'https://example.test/callback', 'client-id', 'client-secret');
})->throws(SlackStatusApiException::class);
