<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\Invitation;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\EventSubscriber\CreateInvitationForNewUserSubscriber;
use App\Shared\DTO\InvitationDTO;
use App\Shared\Facade\EmailFacadeInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->em = mock(EntityManagerInterface::class);
    $this->emailFacade = mock(EmailFacadeInterface::class);

    $this->subscriber = new CreateInvitationForNewUserSubscriber(
        em: $this->em,
        emailFacade: $this->emailFacade,
    );
});

it('creates invitation and sends email for new user', function () {
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@example.com',
        password: 'hashed',
    );

    $event = new AfterEntityPersistedEvent($user);

    $this->em
        ->expects('persist')
        ->once()
        ->withArgs(fn (Invitation $invitation) => $invitation->user === $user);

    $this->em
        ->expects('flush')
        ->once();

    $this->emailFacade
        ->expects('sendInvitationEmail')
        ->once()
        ->withArgs(fn (InvitationDTO $dto) => 'john@example.com' === $dto->user->email);

    $this->subscriber->createInvitationForNewUser($event);
});

it('skips non-User entities', function () {
    $entity = new stdClass();
    $event = new AfterEntityPersistedEvent($entity);

    $this->em->expects('persist')->never();
    $this->em->expects('flush')->never();
    $this->emailFacade->expects('sendInvitationEmail')->never();

    $this->subscriber->createInvitationForNewUser($event);
});
