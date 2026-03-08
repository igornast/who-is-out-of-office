<?php

declare(strict_types=1);

use App\Shared\Service\Ical\IcalHashGenerator;
use App\Shared\Service\Ical\IcalSubscriptionUrlGenerator;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

it('generates a subscription URL for a user', function () {
    $userDTO = UserDTOFixture::create([
        'id' => '123',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ]);
    $secret = 'test-secret';
    $expectedHash = IcalHashGenerator::generateForUser($userDTO, $secret);

    $urlGenerator = Mockery::mock(UrlGeneratorInterface::class);
    $urlGenerator->shouldReceive('generate')
        ->once()
        ->with('app_api_ical_endpoint', [
            'userId' => '123',
            'secret' => $expectedHash,
        ], UrlGeneratorInterface::ABSOLUTE_URL)
        ->andReturn('https://example.com/api/calendar/123/'.$expectedHash.'.ics');

    $generator = new IcalSubscriptionUrlGenerator($urlGenerator, $secret);

    $url = $generator->generateForUser($userDTO);

    expect($url)->toBe('https://example.com/api/calendar/123/'.$expectedHash.'.ics');
});

it('uses icalHashSalt when available', function () {
    $userDTO = UserDTOFixture::create([
        'id' => '456',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
        'icalHashSalt' => 'custom-salt',
    ]);
    $secret = 'test-secret';
    $expectedHash = IcalHashGenerator::generateForUser($userDTO, $secret);

    $urlGenerator = Mockery::mock(UrlGeneratorInterface::class);
    $urlGenerator->shouldReceive('generate')
        ->once()
        ->with('app_api_ical_endpoint', [
            'userId' => '456',
            'secret' => $expectedHash,
        ], UrlGeneratorInterface::ABSOLUTE_URL)
        ->andReturn('https://example.com/api/calendar/456/'.$expectedHash.'.ics');

    $generator = new IcalSubscriptionUrlGenerator($urlGenerator, $secret);

    $url = $generator->generateForUser($userDTO);

    expect($url)->toBe('https://example.com/api/calendar/456/'.$expectedHash.'.ics');
});

it('generates different URLs for different users', function () {
    $secret = 'test-secret';
    $user1 = UserDTOFixture::create([
        'id' => '111',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ]);
    $user2 = UserDTOFixture::create([
        'id' => '222',
        'createdAt' => new DateTimeImmutable('2025-01-15T10:30:00+00:00'),
    ]);

    $urlGenerator = Mockery::mock(UrlGeneratorInterface::class);
    $urlGenerator->shouldReceive('generate')
        ->andReturnUsing(fn (string $route, array $params) => sprintf('https://example.com/api/calendar/%s/%s.ics', $params['userId'], $params['secret']));

    $generator = new IcalSubscriptionUrlGenerator($urlGenerator, $secret);

    $url1 = $generator->generateForUser($user1);
    $url2 = $generator->generateForUser($user2);

    expect($url1)->not()->toBe($url2);
});

it('always generates absolute URLs', function () {
    $userDTO = UserDTOFixture::create(['id' => '123']);
    $secret = 'test-secret';

    $urlGenerator = Mockery::mock(UrlGeneratorInterface::class);
    $urlGenerator->shouldReceive('generate')
        ->once()
        ->with('app_api_ical_endpoint', Mockery::any(), UrlGeneratorInterface::ABSOLUTE_URL)
        ->andReturn('https://example.com/api/calendar/123/hash.ics');

    $generator = new IcalSubscriptionUrlGenerator($urlGenerator, $secret);
    $generator->generateForUser($userDTO);
});
