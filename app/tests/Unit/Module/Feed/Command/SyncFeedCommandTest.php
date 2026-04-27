<?php

declare(strict_types=1);

use App\Module\Feed\Command\SyncFeedCommand;
use App\Shared\Facade\FeedFacadeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;

it('calls facade sync and returns success', function (): void {
    $facade = mock(FeedFacadeInterface::class);
    $facade->expects('sync')->once();
    $logger = mock(LoggerInterface::class);

    $cmd = new SyncFeedCommand($facade, $logger);

    expect(($cmd)())->toBe(Command::SUCCESS);
});

it('logs error and returns failure when sync throws', function (): void {
    $facade = mock(FeedFacadeInterface::class);
    $facade->expects('sync')->once()->andThrow(new RuntimeException('network error'));
    $logger = mock(LoggerInterface::class);
    $logger->expects('error')->once()->withArgs(
        fn (string $msg, array $ctx): bool => str_contains($msg, '[FEED][SYNC]') && 'network error' === $ctx['exception']
    );

    $cmd = new SyncFeedCommand($facade, $logger);

    expect(($cmd)())->toBe(Command::FAILURE);
});
