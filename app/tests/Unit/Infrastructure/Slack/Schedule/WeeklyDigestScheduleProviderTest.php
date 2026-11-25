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

it('returns the same schedule on every call', function (): void {
    $provider = new WeeklyDigestScheduleProvider(mock(DynamicWeeklyDigestTrigger::class));

    expect($provider->getSchedule())->toBe($provider->getSchedule());
});

it('marks the built schedule for a rebuild on reload', function (): void {
    $trigger = mock(DynamicWeeklyDigestTrigger::class);
    $trigger->allows('setNotBefore');
    $provider = new WeeklyDigestScheduleProvider($trigger);
    $schedule = $provider->getSchedule();
    $schedule->setRestart(false);

    $provider->reload(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));

    expect($schedule->shouldRestart())->toBeTrue();
});

it('does not build the schedule on reload', function (): void {
    $trigger = mock(DynamicWeeklyDigestTrigger::class);
    $trigger->allows('setNotBefore');
    $provider = new WeeklyDigestScheduleProvider($trigger);

    $provider->reload(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));

    expect($provider->getSchedule()->getRecurringMessages())->toHaveCount(1);
});
