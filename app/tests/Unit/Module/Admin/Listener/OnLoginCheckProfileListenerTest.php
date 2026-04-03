<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Listener\OnLoginCheckProfileListener;
use App\Module\User\Repository\InvitationRepositoryInterface;
use App\Shared\DTO\InvitationDTO;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

beforeEach(function (): void {
    $this->invitationRepository = mock(InvitationRepositoryInterface::class);
    $this->translator = mock(TranslatorInterface::class);
    $this->translator->allows('trans')->andReturnUsing(fn (string $id) => $id);

    $this->listener = new OnLoginCheckProfileListener(
        invitationRepository: $this->invitationRepository,
        translator: $this->translator,
    );

    $this->user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@whoisooo.app',
        password: 'hashed',
        workingDays: [1, 2, 3, 4, 5],
    );
});

it('allows login when user has no pending invitation', function (): void {
    $this->invitationRepository
        ->expects('findOneByUserId')
        ->with($this->user->id->toString())
        ->andReturn(null);

    $event = mock(LoginSuccessEvent::class);
    $event->allows('getUser')->andReturn($this->user);

    ($this->listener)($event);
})->throwsNoExceptions();

it('throws AccessDeniedHttpException when user has pending invitation', function (): void {
    $invitationDTO = new InvitationDTO(
        id: Uuid::uuid4()->toString(),
        token: 'some-token',
        user: UserDTOFixture::create(['id' => $this->user->id->toString()]),
        createdAt: new DateTimeImmutable(),
    );

    $this->invitationRepository
        ->expects('findOneByUserId')
        ->with($this->user->id->toString())
        ->andReturn($invitationDTO);

    $event = mock(LoginSuccessEvent::class);
    $event->allows('getUser')->andReturn($this->user);

    ($this->listener)($event);
})->throws(AccessDeniedHttpException::class, 'error.account_not_active');
