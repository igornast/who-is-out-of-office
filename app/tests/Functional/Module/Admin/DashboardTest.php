<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;

beforeEach(function (): void {
    $kernel = static::bootKernel();
    $this->entityManager = $kernel->getContainer()
        ->get('doctrine')
        ->getManager();

    $this->adminUser = $this->entityManager
        ->getRepository(User::class)
        ->findOneBy(['email' => 'admin@ooo.com']);
});

it('redirects unauthenticated users to login', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/app/dashboard');

    $content = $client->getCrawler()->text();
    expect($content)
        ->toContain("Who's OOO")
        ->toContain('Sign in');
});

it('displays error message for invalid credentials', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'unknown@ooo.com', '123');

    $content = $client->getCrawler()->text();
    expect($content)->toContain('Invalid credentials');
});

it('displays user information on dashboard', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@ooo.com', '123');

    $client->waitForVisibility('.ooo-sidebar');

    $content = $client->getCrawler()->text();
    expect($content)->toContain('Hans Müller');
});

it('admin can access menu items', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@ooo.com', '123');

    $client->request('GET', '/app/dashboard');

    $content = $client->getCrawler()->text();

    expect($content)
        ->toContain('My Team')
        ->toContain('Leave Types')
        ->toContain('Profile Settings')
        ->toContain('Calendar')
        ->toContain('Absence Requests')
        ->toContain('Awaiting Approval');
});

it('displays upcoming absences in team', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@ooo.com', '123');

    $client->request('GET', '/app/dashboard');

    $content = $client->getCrawler()->text();

    expect($content)
        ->toContain('Petra Schmidt')
        ->toContain('Vacation');
});
