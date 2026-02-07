<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Command;

use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

#[
    AsCommand(name: 'slack:weekly_digest', description: 'Send weekly digest notification'),
    AsCronTask(expression: '15 8 * * MON'),
]
class WeeklyDigestCommand
{
    public function __construct(private readonly SlackFacadeInterface $slackFacade)
    {
    }

    public function __invoke(): int
    {
        $this->slackFacade->sendWeeklyDigestNotification();

        return Command::SUCCESS;
    }
}
