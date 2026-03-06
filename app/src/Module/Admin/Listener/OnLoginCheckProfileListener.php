<?php

declare(strict_types=1);

namespace App\Module\Admin\Listener;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\User\Repository\InvitationRepositoryInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: LoginSuccessEvent::class)]
class OnLoginCheckProfileListener
{
    public function __construct(
        private readonly InvitationRepositoryInterface $invitationRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        $invitation = $this->invitationRepository->findOneByUserId($user->id->toString());

        if (null === $invitation) {
            return;
        }

        throw new AccessDeniedHttpException($this->translator->trans('error.account_not_active'));
    }
}
