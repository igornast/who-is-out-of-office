<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\EventListener;

use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Security\TotpSecretEncryptor;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\AuthenticationTokenCreatedEvent;

class TwoFactorAuthenticationListener
{
    public function __construct(private readonly TotpSecretEncryptor $encryptor)
    {
    }

    #[AsEventListener(
        event: AuthenticationTokenCreatedEvent::class,
        priority: 10,
        dispatcher: 'security.event_dispatcher.main',
    )]
    public function onAuthenticationTokenCreated(AuthenticationTokenCreatedEvent $event): void
    {
        $user = $event->getAuthenticatedToken()->getUser();

        if (!$user instanceof User || !$user->isTwoFactorEnabled || null === $user->totpSecret) {
            return;
        }

        $user->setDecryptedTotpSecret($this->encryptor->decrypt($user->totpSecret));
    }

    #[AsEventListener(event: TwoFactorAuthenticationEvents::SUCCESS)]
    public function onSuccess(TwoFactorAuthenticationEvent $event): void
    {
        $user = $event->getToken()->getUser();

        if (!$user instanceof User) {
            return;
        }

        $user->setDecryptedTotpSecret(null);
    }
}
