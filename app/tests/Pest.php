<?php

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
    $reflection->setAccessible(true);
    $client = $reflection->invoke(null, $options, $kernelOptions, $managerOptions);

    return $client;
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
