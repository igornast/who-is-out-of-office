<?php

declare(strict_types=1);

namespace App\Module\Admin\Listener;

use App\Module\User\Repository\InvitationRepositoryInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
class OnLoginCheckProfileListener
{
    public function __construct(
        private readonly InvitationRepositoryInterface $invitationRepository,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $invitation = $this->invitationRepository->findOneBy(['user' => $event->getUser()]);

        if (null === $invitation) {
            return;
        }

        throw new AccessDeniedHttpException('Your account is not active.');
    }
}
