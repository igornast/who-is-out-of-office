<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;

afterEach(function (): void {
    static::getContainer()
        ->get('doctrine')
        ->getConnection()
        ->executeStatement('UPDATE user SET is_active = 1 WHERE email = ?', ['kwame.mensah@whoisooo.app']);
});

it('rejects form login for a deactivated user', function (): void {
    $client = static::createClient();
    $crawler = $client->request('GET', '/login');

    $form = $crawler->selectButton('Sign in')->form([
        '_username' => 'deactivated@whoisooo.app',
        '_password' => '123',
    ]);
    $client->submit($form);
    $client->followRedirect();

    expect($client->getRequest()->getPathInfo())->toBe('/login')
        ->and($client->getResponse()->getContent())->toContain('Your account is not active.');
});

it('ends the session of a user deactivated while logged in', function (): void {
    $client = static::createClient();
    $entityManager = static::getContainer()->get('doctrine')->getManager();
    $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'kwame.mensah@whoisooo.app']);

    $client->loginUser($user);
    $client->request('GET', '/app/dashboard');

    expect($client->getResponse()->getStatusCode())->toBe(200);

    $user->isActive = false;
    $entityManager->flush();

    $client->request('GET', '/app/dashboard');

    expect($client->getResponse()->getStatusCode())->toBe(302)
        ->and($client->getResponse()->headers->get('Location'))->toContain('/login');
});
