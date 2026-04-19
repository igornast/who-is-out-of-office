<?php

declare(strict_types=1);

namespace App\Module\Admin\EventSubscriber;

use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

class TwoFactorRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $twoFactorLoginLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TwoFactorAuthenticationEvents::ATTEMPT => 'onAttempt',
            TwoFactorAuthenticationEvents::COMPLETE => 'onSuccess',
        ];
    }

    public function onAttempt(TwoFactorAuthenticationEvent $event): void
    {
        $token = $event->getToken();
        $userId = $token->getUserIdentifier();

        $limiter = $this->twoFactorLoginLimiter->create($userId);
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            throw new TooManyLoginAttemptsAuthenticationException((int) ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60));
        }
    }

    public function onSuccess(TwoFactorAuthenticationEvent $event): void
    {
        $token = $event->getToken();
        $userId = $token->getUserIdentifier();

        $limiter = $this->twoFactorLoginLimiter->create($userId);
        $limiter->reset();
    }
}
