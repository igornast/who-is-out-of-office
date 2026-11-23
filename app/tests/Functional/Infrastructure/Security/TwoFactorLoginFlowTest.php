<?php

declare(strict_types=1);

use App\Infrastructure\Security\TotpSecretEncryptor;
use OTPHP\TOTP;
use Psr\Container\ContainerInterface;
use Symfony\Component\Clock\NativeClock;

const TWO_FACTOR_USER_EMAIL = 'kwame.mensah@whoisooo.app';

afterEach(function (): void {
    static::getContainer()
        ->get('doctrine')
        ->getConnection()
        ->executeStatement(
            'UPDATE user SET is_two_factor_enabled = 0, totp_secret = NULL WHERE email = ?',
            [TWO_FACTOR_USER_EMAIL],
        );
});

function enableTwoFactorFor(ContainerInterface $container, string $email, string $plainSecret): void
{
    $encrypted = $container->get(TotpSecretEncryptor::class)->encrypt($plainSecret);

    $container->get('doctrine')
        ->getConnection()
        ->executeStatement(
            'UPDATE user SET is_two_factor_enabled = 1, totp_secret = ? WHERE email = ?',
            [$encrypted, $email],
        );
}

function submitLoginForm($client): void
{
    $crawler = $client->request('GET', '/login');
    $form = $crawler->selectButton('Sign in')->form([
        '_username' => TWO_FACTOR_USER_EMAIL,
        '_password' => '123',
    ]);
    $client->submit($form);
}

function submitFirstFactor($client): void
{
    submitLoginForm($client);
    $client->followRedirect();
    $client->followRedirect();
}

it('completes form login, TOTP check and reaches the dashboard', function (): void {
    $client = static::createClient();
    $secret = 'JBSWY3DPEHPK3PXP';
    enableTwoFactorFor(static::getContainer(), TWO_FACTOR_USER_EMAIL, $secret);

    submitFirstFactor($client);

    expect($client->getRequest()->getPathInfo())->toBe('/2fa');

    $client->request('GET', '/app/dashboard');
    expect($client->getResponse()->isRedirect())->toBeTrue()
        ->and($client->getResponse()->headers->get('Location'))->toContain('/2fa');

    $crawler = $client->request('GET', '/2fa');
    $form = $crawler->selectButton('Verify')->form(['_auth_code' => TOTP::createFromSecret($secret, new NativeClock())->now()]);
    $client->submit($form);

    expect($client->getResponse()->isRedirect())->toBeTrue()
        ->and($client->getResponse()->headers->get('Location'))->toContain('/app/dashboard');

    $client->request('GET', '/app/dashboard');
    expect($client->getResponse()->getStatusCode())->toBe(200);
});

it('keeps the decrypted TOTP secret out of the session right after the first factor login', function (): void {
    $client = static::createClient();
    $secret = 'JBSWY3DPEHPK3PXP';
    enableTwoFactorFor(static::getContainer(), TWO_FACTOR_USER_EMAIL, $secret);

    submitLoginForm($client);

    $stored = serialize($client->getRequest()->getSession()->all());

    expect($stored)->toContain('_security_main')
        ->and($stored)->not->toContain($secret);
});

it('rejects a wrong TOTP code', function (): void {
    $client = static::createClient();
    enableTwoFactorFor(static::getContainer(), TWO_FACTOR_USER_EMAIL, 'JBSWY3DPEHPK3PXP');

    submitFirstFactor($client);
    $crawler = $client->request('GET', '/2fa');
    $form = $crawler->selectButton('Verify')->form(['_auth_code' => '000000']);
    $client->submit($form);
    $client->followRedirect();

    expect($client->getRequest()->getPathInfo())->toBe('/2fa');
});
