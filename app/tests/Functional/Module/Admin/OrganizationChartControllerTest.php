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
        ->findOneBy(['email' => 'admin@whoisooo.app']);
});

it('redirects unauthenticated users to login', function (): void {
    $client = static::createClient();
    $client->request('GET', '/app/organization-chart');

    expect($client->getResponse()->getStatusCode())->toBe(302);
});

it('renders the organization chart page for an authenticated user', function (): void {
    $client = static::createClient();
    $client->loginUser($this->adminUser);
    $client->request('GET', '/app/organization-chart');

    expect($client->getResponse()->getStatusCode())->toBe(200);
});

it('displays the organization chart heading via structural selector', function (): void {
    $client = static::createClient();
    $client->loginUser($this->adminUser);
    $crawler = $client->request('GET', '/app/organization-chart');

    expect($crawler->filter('.card-title')->count())->toBeGreaterThan(0);
    expect($crawler->filter('.card-title')->first()->text())->not->toBeEmpty();
});

it('renders Hans Müller as a root node with the has-children class', function (): void {
    $client = static::createClient();
    $client->loginUser($this->adminUser);
    $crawler = $client->request('GET', '/app/organization-chart');

    // Hans Müller (user_1, ROLE_ADMIN) is the root; Petra Schmidt reports to him,
    // so his node must carry org-node--has-children.
    $hasChildrenNodes = $crawler->filter('.org-node--has-children');
    expect($hasChildrenNodes->count())->toBeGreaterThan(0);

    $names = $hasChildrenNodes->each(fn ($node) => $node->filter('.org-node-name')->first()->text());
    expect($names)->toContain('Hans Müller');
});

it('renders Petra Schmidt nested inside Hans Müller\'s children list', function (): void {
    // Fixture relationship: user_2 (Petra Schmidt) has manager: user_1 (Hans Müller)
    $client = static::createClient();
    $client->loginUser($this->adminUser);
    $crawler = $client->request('GET', '/app/organization-chart');

    // Find all .org-node-name elements that live inside
    // a .org-node--has-children > .org-children subtree.
    $nestedNames = $crawler
        ->filter('.org-node--has-children .org-children .org-node-name')
        ->each(fn ($node) => $node->text());

    expect($nestedNames)->toContain('Petra Schmidt');
});

it('renders a toggle button for nodes that have children', function (): void {
    $client = static::createClient();
    $client->loginUser($this->adminUser);
    $crawler = $client->request('GET', '/app/organization-chart');

    expect($crawler->filter('.org-toggle')->count())->toBeGreaterThan(0);
});

it('renders an admin badge for the admin user', function (): void {
    $client = static::createClient();
    $client->loginUser($this->adminUser);
    $crawler = $client->request('GET', '/app/organization-chart');

    expect($crawler->filter('.org-badge-admin')->count())->toBeGreaterThan(0);
});
