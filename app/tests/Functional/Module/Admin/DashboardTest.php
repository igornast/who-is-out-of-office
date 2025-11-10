<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\User;

beforeEach(function (): void {
    $this->client = createClient($this);
    $this->entityManager = $this->client->getContainer()
        ->get('doctrine')
        ->getManager();

    $this->adminUser = $this->entityManager
        ->getRepository(User::class)
        ->findOneBy(['email' => 'admin@ooo.com']);
});

it('redirects unauthenticated users to login', function (): void {
    $this->client->request('GET', '/app/dashboard');

    expect($this->client->getResponse()->isRedirect())
        ->toBeTrue();
});

it('displays user information on dashboard', function (): void {
    $this->client
        ->loginUser($this->adminUser)
        ->request('GET', '/app/dashboard');

    expect($this->client->getResponse()->isSuccessful())
        ->toBeTrue();

    $content = $this->client->getResponse()->getContent();
    expect($content)
        ->toContain('admin@ooo.com')
        ->toContain('Hans Müller');
});

it('admin can access menu items', function (): void {
    $this->client
        ->loginUser($this->adminUser)
        ->request('GET', '/app/dashboard');

    expect($this->client->getResponse()->isSuccessful())
        ->toBeTrue();

    $content = $this->client->getResponse()->getContent();

    expect($content)
        ->toContain('My Team')
        ->toContain('Leave Types')
        ->toContain('Profile Settings')
        ->toContain('Calendar')
        ->toContain('Absence Requests')
        ->toContain('Requests to approve');
});

it('displays upcoming absences in team', function (): void {
    $this->client
        ->loginUser($this->adminUser)
        ->request('GET', '/app/dashboard');

    expect($this->client->getResponse()->isSuccessful())
        ->toBeTrue();

    $content = $this->client->getResponse()->getContent();

    expect($content)
        ->toContain('Petra Schmidt')
        ->toContain('Vacation')
        ->toContain('Sick Leave');
});
