<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Command;

use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[
    AsCommand(name: 'slack:weekly_digest', description: 'Send weekly digest notification'),
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
