<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\ChangePasswordCommandHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->passwordHasher = mock(UserPasswordHasherInterface::class);
    $this->handler = new ChangePasswordCommandHandler(
        $this->userRepository,
        $this->passwordHasher,
    );
});

it('hashes the plain password and delegates to repository', function () {
    $user = mock(PasswordAuthenticatedUserInterface::class);

    $this->passwordHasher
        ->expects('hashPassword')
        ->once()
        ->with($user, 'new-plain-password')
        ->andReturn('hashed-password-123');

    $this->userRepository
        ->expects('updatePassword')
        ->once()
        ->with('user-1', 'hashed-password-123');

    $this->handler->handle('user-1', 'new-plain-password', $user);
});
