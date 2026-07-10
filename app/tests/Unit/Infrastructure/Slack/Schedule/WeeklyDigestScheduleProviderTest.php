<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Schedule\DynamicWeeklyDigestTrigger;
use App\Infrastructure\Slack\Schedule\WeeklyDigestScheduleProvider;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;

it('schedules the weekly digest command with the dynamic trigger', function (): void {
    $trigger = mock(DynamicWeeklyDigestTrigger::class);
    $provider = new WeeklyDigestScheduleProvider($trigger);

    $schedule = $provider->getSchedule();
    $messages = $schedule->getRecurringMessages();

    expect($schedule)->toBeInstanceOf(Schedule::class)
        ->and($messages)->toHaveCount(1);

    $recurring = $messages[0];
    $context = new MessageContext('weekly_digest', 'weekly_digest', $trigger, new DateTimeImmutable('2026-01-01'));
    $dispatched = [...$recurring->getMessages($context)];

    expect($recurring->getTrigger())->toBe($trigger)
        ->and($dispatched)->toHaveCount(1)
        ->and($dispatched[0])->toEqual(new RunCommandMessage('slack:weekly_digest'));
});
