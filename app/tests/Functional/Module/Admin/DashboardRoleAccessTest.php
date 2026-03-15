<?php

declare(strict_types=1);

afterAll(function (): void {
    createPantherClient()->request('GET', '/logout');
});

it('admin sees full sidebar with settings and organization sections', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@ooo.com', '123');

    $client->request('GET', '/app/dashboard');
    $client->waitForVisibility('.ooo-sidebar');

    $sidebar = $client->getCrawler()->filter('.ooo-sidebar')->text();

    expect($sidebar)
        ->toContain('My Organization')
        ->toContain('Team Leave Requests')
        ->toContain('Team Members')
        ->toContain('App Settings')
        ->toContain('Leave Types')
        ->toContain('Profile Settings')
        ->toContain('Calendar')
        ->toContain('Absence Requests');
});

it('admin sees awaiting approval stat card', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'admin@ooo.com', '123');

    $client->request('GET', '/app/dashboard');

    $content = $client->getCrawler()->text();

    expect($content)->toContain('Awaiting Approval');
});

it('manager sees team section but not settings section', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'manager@ooo.com', '123');

    $client->request('GET', '/app/dashboard');
    $client->waitForVisibility('.ooo-sidebar');

    $sidebar = $client->getCrawler()->filter('.ooo-sidebar')->text();

    expect($sidebar)
        ->toContain('MY TEAM')
        ->toContain('Team Leave Requests')
        ->toContain('Team Members')
        ->toContain('Absence Requests')
        ->toContain('Calendar')
        ->toContain('Profile Settings')
        ->not->toContain('App Settings')
        ->not->toContain('Leave Types');
});

it('manager sees awaiting approval stat card', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'manager@ooo.com', '123');

    $client->request('GET', '/app/dashboard');

    $content = $client->getCrawler()->text();

    expect($content)->toContain('Awaiting Approval');
});

it('regular user sees minimal sidebar without organization or settings', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@ooo.com', '123');

    $client->request('GET', '/app/dashboard');
    $client->waitForVisibility('.ooo-sidebar');

    $sidebar = $client->getCrawler()->filter('.ooo-sidebar')->text();

    expect($sidebar)
        ->toContain('Absence Requests')
        ->toContain('Calendar')
        ->toContain('Profile Settings')
        ->not->toContain('MY TEAM')
        ->not->toContain('Team Leave Requests')
        ->not->toContain('Team Members')
        ->not->toContain('App Settings')
        ->not->toContain('Leave Types');
});

it('regular user sees pending requests instead of awaiting approval', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@ooo.com', '123');

    $client->request('GET', '/app/dashboard');

    $content = $client->getCrawler()->text();

    expect($content)
        ->toContain('Pending Requests')
        ->not->toContain('Awaiting Approval');
});

it('regular user cannot access admin settings page', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@ooo.com', '123');

    $client->request('GET', '/app/settings');

    $content = $client->getCrawler()->text();

    expect($content)->toContain('Access Denied');
});

it('dashboard displays greeting with user first name', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'manager@ooo.com', '123');

    $client->request('GET', '/app/dashboard');

    $content = $client->getCrawler()->text();

    expect($content)->toContain('Petra');
});

it('dashboard displays vacation balance for logged-in user', function (): void {
    $client = createPantherClient();
    loginUserWithLoginForm($client, 'user@ooo.com', '123');

    $client->request('GET', '/app/dashboard');

    $content = $client->getCrawler()->text();

    expect($content)
        ->toContain('My Vacation Balance')
        ->toContain('24');
});
