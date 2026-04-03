<?php

use App\Infrastructure\Doctrine\Entity\PasswordResetToken;
use App\Infrastructure\Doctrine\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\PantherTestCase;

uses(PantherTestCase::class)
    ->beforeAll(fn () => self::ensureKernelShutdown())
    ->in('Functional');

function srcDirectory(string $path): string
{
    return dirname(__FILE__, 2).'/src'.$path;
}

function modules(): array
{
    return array_diff(scandir(srcDirectory('/Module')), ['.', '..']);
}

function createPantherClient(array $options = [], array $kernelOptions = [], array $managerOptions = []): Client
{
    $reflection = new ReflectionMethod(PantherTestCase::class, 'createPantherClient');
    $client = $reflection->invoke(null, $options, $kernelOptions, $managerOptions);

    return $client;
}

function createPasswordResetToken(EntityManagerInterface $em, string $userEmail): string
{
    $user = $em->getRepository(User::class)->findOneBy(['email' => $userEmail]);
    $rawToken = bin2hex(random_bytes(32));

    $entity = new PasswordResetToken(
        id: Uuid::uuid4(),
        token: hash('sha256', $rawToken),
        user: $user,
        expiresAt: new DateTimeImmutable('+1 hour'),
    );

    $em->persist($entity);
    $em->flush();

    return $rawToken;
}

function loginUserWithLoginForm(Client $client, string $email, string $password): void
{
    $crawler = $client->request('GET', '/login');

    $loginForm = $crawler->selectButton('Sign in')->form();
    $loginForm['_username'] = $email;
    $loginForm['_password'] = $password;
    //    $client->takeScreenshot('tests/_output/01-login-form.png');

    $client->submit($loginForm);

    $client->waitForVisibility('body');
}
