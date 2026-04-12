<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Command\SlackStatusSyncCommand;
use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\Console\Command\Command;

it('delegates to the facade and returns success', function (): void {
    $facade = mock(SlackFacadeInterface::class);
    $facade->expects('syncStatuses')->once();

    $command = new SlackStatusSyncCommand($facade);

    expect($command())->toBe(Command::SUCCESS);
});
