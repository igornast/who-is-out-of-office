<?php

declare(strict_types=1);

namespace App\Module\Admin\EventSubscriber;

use App\Infrastructure\Doctrine\Entity\Invitation;
use App\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Ramsey\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CreateInvitationForNewUserSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => 'createInvitationForNewUser',
        ];
    }

    /**
     * @param AfterEntityPersistedEvent<User|object> $event
     */
    public function createInvitationForNewUser(AfterEntityPersistedEvent $event): void
    {
        $user = $event->getEntityInstance();

        if (!$user instanceof User) {
            return;
        }

        $invitation = new Invitation(
            id: Uuid::uuid4(),
            token: Uuid::uuid4()->toString(),
            user: $user,
        );

        $this->em->persist($invitation);
        $this->em->flush();
    }
}
