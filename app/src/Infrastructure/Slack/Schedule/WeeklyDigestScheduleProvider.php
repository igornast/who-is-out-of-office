<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Schedule;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('weekly_digest')]
class WeeklyDigestScheduleProvider implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function __construct(private readonly DynamicWeeklyDigestTrigger $trigger)
    {
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= new Schedule()->add(
            RecurringMessage::trigger(
                trigger: $this->trigger,
                message: new RunCommandMessage('slack:weekly_digest'),
            ),
        );
    }

    public function reload(?\DateTimeImmutable $notBefore = null): void
    {
        $this->trigger->setNotBefore($notBefore);

        $this->schedule?->setRestart(true);
    }
}
