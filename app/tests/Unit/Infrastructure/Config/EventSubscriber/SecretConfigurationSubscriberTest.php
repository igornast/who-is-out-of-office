<?php

declare(strict_types=1);

use App\Infrastructure\Config\EventSubscriber\SecretConfigurationSubscriber;
use App\Shared\Enum\AppEnvironmentEnum;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;

it('logs an error when prod uses a short iCal secret', function (): void {
    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->withArgs(
        fn (string $message): bool => str_contains($message, '[CONFIG][ICAL-SECRET]')
            && str_contains($message, '32')
            && !str_contains($message, 'not-so-secret')
    );

    new SecretConfigurationSubscriber('not-so-secret', AppEnvironmentEnum::PROD->value, $logger)->warnAboutWeakSecrets();
});

it('logs an error when prod has an empty iCal secret', function (): void {
    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->withArgs(
        fn (string $message): bool => str_contains($message, '[CONFIG][ICAL-SECRET]')
    );

    new SecretConfigurationSubscriber('', AppEnvironmentEnum::PROD->value, $logger)->warnAboutWeakSecrets();
});

it('stays quiet when prod has a long enough iCal secret', function (): void {
    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->never();

    new SecretConfigurationSubscriber(str_repeat('a', 32), AppEnvironmentEnum::PROD->value, $logger)->warnAboutWeakSecrets();
});

it('stays quiet outside prod', function (): void {
    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->never();

    new SecretConfigurationSubscriber('not-so-secret', 'dev', $logger)->warnAboutWeakSecrets();
});

it('subscribes to the console command event', function (): void {
    expect(SecretConfigurationSubscriber::getSubscribedEvents())->toHaveKey(ConsoleEvents::COMMAND);
});
