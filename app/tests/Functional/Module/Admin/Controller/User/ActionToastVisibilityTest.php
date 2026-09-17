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
});

it('renders the toast outside the modal', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    $client->waitForVisibility('.ooo-sidebar');

    $crawler = $client->getCrawler();

    expect($crawler->filter('#action-confirm-modal .widget-toast')->count())->toBe(0)
        ->and($crawler->filter('[data-controller="action-confirm"] > .widget-toast')->count())->toBe(1);
});

it('shows the error toast when resetting the password of an inactive user', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@whoisooo.app', '123');

    $client->waitForVisibility('.ooo-sidebar');

    $client->request('GET', sprintf('/app/dashboard/my-organization/%s/edit', $this->invitedUser->id->toString()));

    $client->waitForVisibility('[data-lr-action="resetPassword"]');
    $client->executeScript("document.querySelector('[data-lr-action=\"resetPassword\"]').click();");

    $client->waitForVisibility('#action-confirm-modal.show [data-modal="confirm"]');
    $client->executeScript("document.querySelector('#action-confirm-modal [data-modal=\"confirm\"]').click();");

    $client->waitForVisibility('.widget-toast--visible');

    $toast = $client->getCrawler()->filter('.widget-toast--visible');

    expect($toast->count())->toBe(1)
        ->and($toast->text())->toContain('Cannot reset password for an inactive user');
});
