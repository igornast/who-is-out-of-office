<?php

declare(strict_types=1);

use App\Infrastructure\Config\EventSubscriber\BaseUrlConfigurationSubscriber;
use Psr\Log\LoggerInterface;

it('logs an error when prod still points at localhost', function (): void {
    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->withArgs(
        fn (string $message): bool => str_contains($message, '[CONFIG][BASE-URL]')
            && str_contains($message, 'http://localhost')
    );

    new BaseUrlConfigurationSubscriber('http://localhost', 'prod', $logger)->warnAboutLocalBaseUrl();
});

it('stays quiet when prod has a real base URL', function (): void {
    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->never();

    new BaseUrlConfigurationSubscriber('https://leave.example.com', 'prod', $logger)->warnAboutLocalBaseUrl();
});

it('stays quiet outside prod', function (): void {
    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->never();

    new BaseUrlConfigurationSubscriber('http://localhost', 'dev', $logger)->warnAboutLocalBaseUrl();
});

it('detects a local host regardless of casing', function (string $baseUrl): void {
    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->withArgs(
        fn (string $message): bool => str_contains($message, 'is still')
    );

    new BaseUrlConfigurationSubscriber($baseUrl, 'prod', $logger)->warnAboutLocalBaseUrl();
})->with([
    'upper-case scheme and host' => ['HTTP://LOCALHOST'],
    'mixed-case host' => ['https://LocalHost'],
    'ipv6 loopback' => ['http://[::1]:8080'],
    'ipv4 loopback' => ['http://127.0.0.1'],
]);

it('logs an error when the base URL has no parsable host', function (string $baseUrl): void {
    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->withArgs(
        fn (string $message): bool => str_contains($message, '[CONFIG][BASE-URL]')
            && str_contains($message, 'no parsable host')
    );

    new BaseUrlConfigurationSubscriber($baseUrl, 'prod', $logger)->warnAboutLocalBaseUrl();
})->with([
    'schemeless host' => ['localhost'],
    'schemeless domain' => ['leave.example.com'],
    'empty value' => [''],
    'path only' => ['/app/dashboard'],
]);

it('subscribes to the console command event', function (): void {
    expect(BaseUrlConfigurationSubscriber::getSubscribedEvents())
        ->toHaveKey(Symfony\Component\Console\ConsoleEvents::COMMAND);
});
