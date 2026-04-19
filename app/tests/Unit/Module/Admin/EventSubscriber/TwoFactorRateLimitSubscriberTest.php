<?php

declare(strict_types=1);

use App\Module\Admin\EventSubscriber\TwoFactorRateLimitSubscriber;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

function makeRateLimiterFactory(int $limit = 5): RateLimiterFactory
{
    return new RateLimiterFactory(
        [
            'id' => 'two_factor_login_test',
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => '15 minutes',
        ],
        new InMemoryStorage(),
    );
}

function makeRateLimitEvent(string $userIdentifier = 'john@example.com'): TwoFactorAuthenticationEvent
{
    $token = mock(TokenInterface::class);
    $token->allows('getUserIdentifier')->andReturn($userIdentifier);

    return new TwoFactorAuthenticationEvent(new Request(), $token);
}

it('subscribes to 2FA attempt and success events', function (): void {
    $events = TwoFactorRateLimitSubscriber::getSubscribedEvents();

    expect($events)->toHaveKey(TwoFactorAuthenticationEvents::ATTEMPT)
        ->and($events[TwoFactorAuthenticationEvents::ATTEMPT])->toBe('onAttempt')
        ->and($events)->toHaveKey(TwoFactorAuthenticationEvents::COMPLETE)
        ->and($events[TwoFactorAuthenticationEvents::COMPLETE])->toBe('onSuccess');
});

it('consumes a token from the limiter on attempt when within the limit', function (): void {
    $subscriber = new TwoFactorRateLimitSubscriber(makeRateLimiterFactory(limit: 3));

    $subscriber->onAttempt(makeRateLimitEvent());
})->throwsNoExceptions();

it('throws TooManyLoginAttemptsAuthenticationException once the limit is exceeded', function (): void {
    $subscriber = new TwoFactorRateLimitSubscriber(makeRateLimiterFactory(limit: 2));

    $subscriber->onAttempt(makeRateLimitEvent());
    $subscriber->onAttempt(makeRateLimitEvent());
    $subscriber->onAttempt(makeRateLimitEvent());
})->throws(TooManyLoginAttemptsAuthenticationException::class);

it('keys the limiter by user identifier so different users do not share a bucket', function (): void {
    $subscriber = new TwoFactorRateLimitSubscriber(makeRateLimiterFactory(limit: 1));

    $subscriber->onAttempt(makeRateLimitEvent('alice@example.com'));
    $subscriber->onAttempt(makeRateLimitEvent('bob@example.com'));
})->throwsNoExceptions();

it('resets the limiter on successful 2FA so the user can attempt again', function (): void {
    $subscriber = new TwoFactorRateLimitSubscriber(makeRateLimiterFactory(limit: 1));

    $subscriber->onAttempt(makeRateLimitEvent());
    $subscriber->onSuccess(makeRateLimitEvent());
    $subscriber->onAttempt(makeRateLimitEvent());
})->throwsNoExceptions();
