<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Command;

use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[
    AsCommand(name: 'slack:sync-statuses', description: 'Sync Slack statuses for users starting or ending leave'),
    AsPeriodicTask(frequency: '20 minutes'),
]
class SlackStatusSyncCommand
{
    public function __construct(private readonly SlackFacadeInterface $slackFacade)
    {
    }

    public function __invoke(): int
    {
        $this->slackFacade->syncStatuses();

        return Command::SUCCESS;
    }
}
