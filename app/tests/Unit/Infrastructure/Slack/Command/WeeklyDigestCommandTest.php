<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Command\WeeklyDigestCommand;
use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Component\Console\Command\Command;

beforeEach(function (): void {
    $this->slackFacade = mock(SlackFacadeInterface::class);

    $this->command = new WeeklyDigestCommand(slackFacade: $this->slackFacade);
});

it('calls send weekly digest notification and returns success', function () {
    $this->slackFacade
        ->expects('sendWeeklyDigestNotification')
        ->once();

    $result = ($this->command)();

    expect($result)->toBe(Command::SUCCESS);
});
