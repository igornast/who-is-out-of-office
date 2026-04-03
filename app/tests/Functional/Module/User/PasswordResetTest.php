<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\PasswordResetToken;
use App\Infrastructure\Doctrine\Entity\User;

beforeEach(function (): void {
    $kernel = static::bootKernel();
    $this->entityManager = $kernel->getContainer()
        ->get('doctrine')
        ->getManager();
});

it('displays the password reset request form', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/password-reset/request');

    $content = $client->getCrawler()->text();

    expect($content)
        ->toContain('Forgot Password')
        ->toContain('Email Address')
        ->toContain('Send Reset Link');
});

it('submits email and shows success flash message', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/password-reset/request');

    $client->waitFor('button[type="submit"]');

    $client->executeScript("
        document.querySelector('#password_reset_request_email').value = 'admin@whoisooo.app';
    ");

    $client->getCrawler()->filter('button[type="submit"]')->click();

    $client->waitFor('.alert-success');

    $content = $client->getCrawler()->text();
    expect($content)->toContain('password reset link');
});

it('creates a password reset token in the database after request', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/password-reset/request');

    $client->waitFor('button[type="submit"]');

    $client->executeScript("
        document.querySelector('#password_reset_request_email').value = 'admin@whoisooo.app';
    ");

    $client->getCrawler()->filter('button[type="submit"]')->click();

    $client->waitFor('.alert-success');

    $this->entityManager->clear();

    $user = $this->entityManager
        ->getRepository(User::class)
        ->findOneBy(['email' => 'admin@whoisooo.app']);

    $token = $this->entityManager
        ->getRepository(PasswordResetToken::class)
        ->findOneBy(['user' => $user]);

    expect($token)->not->toBeNull()
        ->and($token->expiresAt)->toBeGreaterThan(new DateTimeImmutable());
});

it('shows success flash even for non-existent email', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/password-reset/request');

    $client->waitFor('button[type="submit"]');

    $client->executeScript("
        document.querySelector('#password_reset_request_email').value = 'nonexistent@example.com';
    ");

    $client->getCrawler()->filter('button[type="submit"]')->click();

    $client->waitFor('.alert-success');

    $content = $client->getCrawler()->text();
    expect($content)->toContain('password reset link');
});

it('displays the password reset form for a valid token', function (): void {
    $token = createPasswordResetToken($this->entityManager, 'admin@whoisooo.app');

    $client = createPantherClient();
    $client->request('GET', '/password-reset/'.$token);

    $content = $client->getCrawler()->text();

    expect($content)
        ->toContain('Reset Password')
        ->toContain('New Password')
        ->toContain('Confirm New Password');
});

it('returns 404 for an invalid token', function (): void {
    $client = createPantherClient();
    $client->request('GET', '/password-reset/'.str_repeat('a', 64));

    $content = $client->getCrawler()->text();

    expect($content)->toContain('NotFoundHttpException');
});

it('shows validation error when passwords do not match', function (): void {
    $token = createPasswordResetToken($this->entityManager, 'admin@whoisooo.app');

    $client = createPantherClient();
    $client->request('GET', '/password-reset/'.$token);

    $client->waitFor('button[type="submit"]');

    $client->executeScript("
        document.querySelector('#password_reset_password_first').value = 'password123';
        document.querySelector('#password_reset_password_second').value = 'differentPassword';
    ");

    $client->getCrawler()->filter('button[type="submit"]')->click();

    $client->waitFor('.invalid-feedback', 10);

    $content = $client->getCrawler()->text();

    expect($content)->toContain('password_reset.error.passwords_must_match');
});

// This test changes the user's password, so it must run last
it('resets password and can log in with the new password', function (): void {
    $token = createPasswordResetToken($this->entityManager, 'admin@whoisooo.app');

    $client = createPantherClient();
    $client->request('GET', '/password-reset/'.$token);

    $client->waitFor('button[type="submit"]');

    $client->executeScript("
        document.querySelector('#password_reset_password_first').value = 'newSecurePass123';
        document.querySelector('#password_reset_password_second').value = 'newSecurePass123';
    ");

    $client->getCrawler()->filter('button[type="submit"]')->click();

    // Wait for redirect to login page before making a new request
    $client->waitFor('.login-card', 10);

    loginUserWithLoginForm($client, 'admin@whoisooo.app', 'newSecurePass123');

    $content = $client->getCrawler()->text();
    expect($content)->toContain('Dashboard');
});
