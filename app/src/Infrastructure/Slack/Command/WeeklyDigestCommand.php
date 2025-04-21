<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Command;

use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'slack:weekly_digest', description: 'Send weekly digest notification')]
class WeeklyDigestCommand extends Command
{
    public function __construct(private readonly SlackFacadeInterface $slackFacade)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $this->slackFacade->sendWeeklyDigestNotification();

        return Command::SUCCESS;
    }
}
