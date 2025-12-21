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

afterEach(fn () => self::ensureKernelShutdown());

it('redirects unauthenticated users to login', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/app/dashboard');

    expect($client->getCurrentURL())
        ->toContain('/login');
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

    $content = $client->getCrawler()->text();
    expect($content)
        ->toContain('admin@ooo.com')
        ->toContain('Hans Müller');
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
        ->toContain('Requests to approve');
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
