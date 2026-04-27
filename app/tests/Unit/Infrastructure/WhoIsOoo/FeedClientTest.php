<?php

declare(strict_types=1);

use App\Infrastructure\WhoIsOoo\FeedClient;
use App\Shared\Enum\AppEnvironmentEnum;
use App\Shared\Enum\FeedItemTypeEnum;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

beforeEach(function (): void {
    $this->httpClient = mock(HttpClientInterface::class);
    $this->logger = mock(LoggerInterface::class);
    $this->client = new FeedClient(
        environment: AppEnvironmentEnum::PROD->value,
        feedUrl: 'https://whoisooo.app/api/feed.json',
        timeoutSeconds: 5,
        httpClient: $this->httpClient,
        logger: $this->logger,
    );
});

it('returns parsed FeedItemDTO array on 200 success', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn([
        'version' => 'https://jsonfeed.org/version/1.1',
        'items' => [
            [
                'id' => 'blog-1',
                'url' => 'https://whoisooo.app/blog/1',
                'title' => 'Post 1',
                'summary' => 'sum',
                '_content_type' => 'blog',
                'date_published' => '2026-04-15T10:00:00Z',
            ],
            [
                'id' => 'change-1',
                'url' => 'https://whoisooo.app/changelog#v1',
                'title' => 'v1.0',
                '_content_type' => 'changelog',
                'date_published' => '2026-04-10T09:00:00Z',
            ],
        ],
    ]);

    $this->httpClient
        ->expects('request')
        ->once()
        ->with('GET', 'https://whoisooo.app/api/feed.json', Mockery::on(
            fn (array $opts) => 0 === ($opts['max_redirects'] ?? null)
                && 5 === ($opts['timeout'] ?? null)
        ))
        ->andReturn($response);

    $result = $this->client->fetch();

    expect($result)->toHaveCount(2)
        ->and($result[0]->externalId)->toBe('blog-1')
        ->and($result[0]->contentType)->toBe(FeedItemTypeEnum::Blog)
        ->and($result[1]->contentType)->toBe(FeedItemTypeEnum::Changelog);
});

it('returns empty array and logs error on non-200', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(503);

    $this->httpClient->allows('request')->andReturn($response);
    $this->logger->expects('error')->once();

    expect($this->client->fetch())->toBe([]);
});

it('returns empty array and logs error on transport exception', function (): void {
    $this->httpClient
        ->allows('request')
        ->andThrow(new TransportException('connect timeout'));
    $this->logger->expects('error')->once();

    expect($this->client->fetch())->toBe([]);
});

it('returns empty array and logs error on malformed payload', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn(['no_items_key' => true]);
    $this->httpClient->allows('request')->andReturn($response);
    $this->logger->expects('error')->once();

    expect($this->client->fetch())->toBe([]);
});

it('skips items missing required fields, logs error per skip', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn([
        'items' => [
            ['id' => 'good', 'url' => 'https://x', 'title' => 't', 'date_published' => '2026-04-15T10:00:00Z'],
            ['id' => 'no-url', 'title' => 't', 'date_published' => '2026-04-15T10:00:00Z'],
            ['id' => 'no-date', 'url' => 'https://x', 'title' => 't'],
        ],
    ]);
    $this->httpClient->allows('request')->andReturn($response);
    $this->logger->expects('error')->twice()->with(
        '[FEED][FETCH]: Skipping invalid feed item.',
        Mockery::on(fn (array $ctx): bool => 'missing_field' === $ctx['reason']
                && isset($ctx['field'])
                && array_key_exists('external_id', $ctx))
    );

    $result = $this->client->fetch();

    expect($result)->toHaveCount(1)
        ->and($result[0]->externalId)->toBe('good');
});

it('skips items with non-http(s) URL scheme', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn([
        'items' => [
            ['id' => 'good', 'url' => 'https://x', 'title' => 't', 'date_published' => '2026-04-15T10:00:00Z'],
            ['id' => 'bad-js', 'url' => 'javascript:alert(1)', 'title' => 't', 'date_published' => '2026-04-15T10:00:00Z'],
            ['id' => 'bad-ftp', 'url' => 'ftp://x/y', 'title' => 't', 'date_published' => '2026-04-15T10:00:00Z'],
        ],
    ]);
    $this->httpClient->allows('request')->andReturn($response);
    $this->logger->expects('error')->twice()->with(
        '[FEED][FETCH]: Skipping invalid feed item.',
        Mockery::on(fn (array $ctx) => 'bad_url' === $ctx['reason'])
    );

    $result = $this->client->fetch();

    expect($result)->toHaveCount(1)
        ->and($result[0]->externalId)->toBe('good');
});

it('skips items with malformed date_published', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn([
        'items' => [
            ['id' => 'good', 'url' => 'https://x', 'title' => 't', 'date_published' => '2026-04-15T10:00:00Z'],
            ['id' => 'bad-date', 'url' => 'https://x', 'title' => 't', 'date_published' => 'not-a-date'],
        ],
    ]);
    $this->httpClient->allows('request')->andReturn($response);
    $this->logger->expects('error')->once()->with(
        '[FEED][FETCH]: Skipping invalid feed item.',
        Mockery::on(fn (array $ctx) => 'bad_date' === $ctx['reason'])
    );

    $result = $this->client->fetch();

    expect($result)->toHaveCount(1)
        ->and($result[0]->externalId)->toBe('good');
});

it('mixed valid and invalid items still produces valid items', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn([
        'items' => [
            ['id' => 'good', 'url' => 'https://x', 'title' => 't', 'date_published' => '2026-04-15T10:00:00Z'],
            ['id' => 'bad-url', 'url' => 'ftp://x/y', 'title' => 't', 'date_published' => '2026-04-15T10:00:00Z'],
            ['id' => 'bad-date', 'url' => 'https://x', 'title' => 't', 'date_published' => 'not-a-date'],
            ['id' => 'no-title', 'url' => 'https://x', 'title' => '', 'date_published' => '2026-04-15T10:00:00Z'],
        ],
    ]);
    $this->httpClient->allows('request')->andReturn($response);
    $this->logger->expects('error')->times(3);

    $result = $this->client->fetch();

    expect($result)->toHaveCount(1)
        ->and($result[0]->externalId)->toBe('good');
});

it('returns empty array and logs error if feedUrl is empty', function (): void {
    $client = new FeedClient(
        environment: AppEnvironmentEnum::PROD->value,
        feedUrl: '',
        timeoutSeconds: 5,
        httpClient: $this->httpClient,
        logger: $this->logger,
    );
    $this->logger->expects('error')->once();
    $this->httpClient->shouldNotReceive('request');

    expect($client->fetch())->toBe([]);
});

it('returns empty and logs error when WHOISOOO_FEED_URL is not https in prod', function (): void {
    $client = new FeedClient(
        environment: AppEnvironmentEnum::PROD->value,
        feedUrl: 'http://whoisooo.app/api/feed.json',
        timeoutSeconds: 5,
        httpClient: $this->httpClient,
        logger: $this->logger,
    );
    $this->logger->expects('error')->once()->with('[FEED][FETCH]: Feed URL must use https scheme.', Mockery::any());
    $this->httpClient->shouldNotReceive('request');

    expect($client->fetch())->toBe([]);
});

it('returns empty and logs error when WHOISOOO_FEED_URL has no scheme in prod', function (): void {
    $client = new FeedClient(
        environment: AppEnvironmentEnum::PROD->value,
        feedUrl: 'whoisooo.app/api/feed.json',
        timeoutSeconds: 5,
        httpClient: $this->httpClient,
        logger: $this->logger,
    );
    $this->logger->expects('error')->once()->with('[FEED][FETCH]: Feed URL must use https scheme.', Mockery::any());
    $this->httpClient->shouldNotReceive('request');

    expect($client->fetch())->toBe([]);
});

it('accepts non-https feed URL outside of prod', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn(['items' => []]);

    $client = new FeedClient(
        environment: 'dev',
        feedUrl: 'http://whoisooo.app/api/feed.json',
        timeoutSeconds: 5,
        httpClient: $this->httpClient,
        logger: $this->logger,
    );

    $this->httpClient
        ->expects('request')
        ->once()
        ->with('GET', 'http://whoisooo.app/api/feed.json', Mockery::any())
        ->andReturn($response);

    $this->logger->shouldNotReceive('error');

    expect($client->fetch())->toBe([]);
});

it('accepts uppercase HTTPS scheme in feed URL', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn(['items' => []]);

    $client = new FeedClient(
        environment: AppEnvironmentEnum::PROD->value,
        feedUrl: 'HTTPS://whoisooo.app/api/feed.json',
        timeoutSeconds: 5,
        httpClient: $this->httpClient,
        logger: $this->logger,
    );

    $this->httpClient
        ->expects('request')
        ->once()
        ->andReturn($response);

    $this->logger->shouldNotReceive('error');

    expect($client->fetch())->toBe([]);
});

it('accepts uppercase HTTPS scheme on feed item URLs', function (): void {
    $response = mock(ResponseInterface::class);
    $response->allows('getStatusCode')->andReturn(200);
    $response->allows('toArray')->andReturn([
        'items' => [
            [
                'id' => 'cap-1',
                'url' => 'HTTPS://whoisooo.app/blog/cap',
                'title' => 'Caps Post',
                'date_published' => '2026-04-15T10:00:00Z',
            ],
        ],
    ]);

    $this->httpClient->allows('request')->andReturn($response);
    $this->logger->shouldNotReceive('error');

    $result = $this->client->fetch();

    expect($result)->toHaveCount(1)
        ->and($result[0]->externalId)->toBe('cap-1');
});
