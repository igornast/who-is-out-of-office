<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\Invitation;
use App\Infrastructure\Doctrine\Entity\User;

beforeEach(function (): void {
    $kernel = static::bootKernel();
    $this->entityManager = $kernel->getContainer()
        ->get('doctrine')
        ->getManager();
});

it('displays the invitation form for a valid token', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/invitation/test-invitation-token-abc123');

    $content = $client->getCrawler()->text();

    expect($content)
        ->toContain('Complete Your Account')
        ->toContain('First Name')
        ->toContain('Last Name')
        ->toContain('Date of Birth')
        ->toContain('Password')
        ->toContain('Repeat Password')
        ->toContain('Create Account');
});

it('returns 404 for an invalid token', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/invitation/invalid-token-that-does-not-exist');

    $content = $client->getCrawler()->text();

    expect($content)->toContain('NotFoundHttpException');
});

it('completes the invitation and activates the user account', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/invitation/test-invitation-token-abc123');

    $client->waitFor('button[type="submit"]');

    $client->executeScript("
        document.querySelector('#user_invitation_firstName').value = 'Jane';
        document.querySelector('#user_invitation_lastName').value = 'Smith';
        document.querySelector('#user_invitation_password_first').value = 'securePass123';
        document.querySelector('#user_invitation_password_second').value = 'securePass123';
    ");

    $client->getCrawler()->filter('button[type="submit"]')->click();

    $client->waitFor('.alert-success');

    $content = $client->getCrawler()->text();
    expect($content)->toContain('Account activated');

    $this->entityManager->clear();

    $user = $this->entityManager
        ->getRepository(User::class)
        ->findOneBy(['email' => 'invited@whoisooo.app']);

    expect($user)
        ->firstName->toBe('Jane')
        ->lastName->toBe('Smith')
        ->isActive->toBeTrue()
        ->birthDate->toBeNull();

    $invitation = $this->entityManager
        ->getRepository(Invitation::class)
        ->findOneBy(['token' => 'test-invitation-token-abc123']);

    expect($invitation)->toBeNull();
});

it('shows validation error when passwords do not match', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/invitation/test-invitation-token-validation');

    $client->waitFor('button[type="submit"]');

    $client->executeScript("
        document.querySelector('#user_invitation_firstName').value = 'Jane';
        document.querySelector('#user_invitation_lastName').value = 'Smith';
        document.querySelector('#user_invitation_password_first').value = 'password123';
        document.querySelector('#user_invitation_password_second').value = 'differentPassword';
    ");

    $client->getCrawler()->filter('button[type="submit"]')->click();

    $client->waitFor('.invalid-feedback', 10);

    $content = $client->getCrawler()->text();

    expect($content)->toContain('Passwords must match');
});
