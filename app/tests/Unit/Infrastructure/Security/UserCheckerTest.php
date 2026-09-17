<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Security\UserChecker;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;

beforeEach(function (): void {
    $this->user = new User(
        id: Uuid::uuid4(),
        firstName: 'John',
        lastName: 'Doe',
        email: 'john@whoisooo.app',
        password: 'hashed',
        isActive: true,
    );
});

it('lets an active user authenticate', function (): void {
    new UserChecker()->checkPreAuth($this->user);
})->throwsNoExceptions();

it('rejects an inactive user', function (): void {
    $this->user->isActive = false;

    new UserChecker()->checkPreAuth($this->user);
})->throws(CustomUserMessageAccountStatusException::class, 'error.account_not_active');

it('ignores users that are not application users', function (): void {
    new UserChecker()->checkPreAuth(new InMemoryUser('someone', 'secret'));
})->throwsNoExceptions();

it('has no post-authentication checks', function (): void {
    $this->user->isActive = false;

    new UserChecker()->checkPostAuth($this->user);
})->throwsNoExceptions();
