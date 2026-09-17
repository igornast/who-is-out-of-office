<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;

beforeEach(function (): void {
    $kernel = static::bootKernel();
    $this->entityManager = $kernel->getContainer()
        ->get('doctrine')
        ->getManager();

    $this->invitedUser = $this->entityManager
        ->getRepository(User::class)
        ->findOneBy(['email' => 'invited@whoisooo.app']);

    $this->activeUser = $this->entityManager
        ->getRepository(User::class)
        ->findOneBy(['email' => 'user@whoisooo.app']);

    $this->deactivatedUser = $this->entityManager
        ->getRepository(User::class)
        ->findOneBy(['email' => 'deactivated@whoisooo.app']);
});

it('offers the resend action for an inactive user', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    $client->waitForVisibility('.ooo-sidebar');
    $client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $this->invitedUser->id->toString()));
    $client->waitForVisibility('[data-lr-action="resetPassword"]');

    expect($client->getCrawler()->filter('[data-lr-action="resendInvitation"]')->count())->toBe(1);
});

it('hides the resend action for an active user', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    $client->waitForVisibility('.ooo-sidebar');
    $client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $this->activeUser->id->toString()));
    $client->waitForVisibility('[data-lr-action="resetPassword"]');

    expect($client->getCrawler()->filter('[data-lr-action="resendInvitation"]')->count())->toBe(0);
});

it('hides the resend action for a deactivated user without a pending invitation', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    $client->waitForVisibility('.ooo-sidebar');
    $client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $this->deactivatedUser->id->toString()));
    $client->waitForVisibility('[data-lr-action="resetPassword"]');

    expect($client->getCrawler()->filter('[data-lr-action="resendInvitation"]')->count())->toBe(0);
});
