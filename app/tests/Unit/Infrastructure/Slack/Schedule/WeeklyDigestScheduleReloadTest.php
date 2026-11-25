<?php

declare(strict_types=1);

use App\Infrastructure\Slack\EventListener\WeeklyDigestScheduleReloadListener;
use App\Infrastructure\Slack\Schedule\DynamicWeeklyDigestTrigger;
use App\Infrastructure\Slack\Schedule\WeeklyDigestScheduleProvider;
use App\Shared\Facade\AppSettingsFacadeInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Scheduler\Generator\MessageGenerator;

beforeEach(function (): void {
    $this->cron = '15 8 * * MON';
    $this->shouldFail = false;
    $this->clock = new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturnUsing(function (): string {
        if ($this->shouldFail) {
            throw new RuntimeException('broken');
        }

        return $this->cron;
    });
    $facade->allows('weeklyDigestTimezone')->andReturnUsing(function (): string {
        if ($this->shouldFail) {
            throw new RuntimeException('broken');
        }

        return 'UTC';
    });
    $this->provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($facade));
    $this->generator = new MessageGenerator($this->provider, 'weekly_digest', $this->clock);
    $this->listener = new WeeklyDigestScheduleReloadListener($facade, $this->provider, new NullLogger(), $this->clock);
    $this->worker = new Worker(['scheduler_weekly_digest' => mock(ReceiverInterface::class)], mock(MessageBusInterface::class));
    $this->listener->onWorkerStarted(new WorkerStartedEvent($this->worker));
});

function weeklyDigestsSentAt(MessageGenerator $generator, MockClock $clock, string $at): int
{
    $clock->modify($at);

    return iterator_count($generator->getMessages());
}

function readWeeklyDigestSettingsAt(MockClock $clock, WeeklyDigestScheduleReloadListener $listener, Worker $worker, string $at): void
{
    $clock->modify($at);
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
}

it('sends the next digest at the new slot and not at the old one', function (): void {
    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-17 09:00:00'))->toBe(0);

    $this->cron = '0 10 * * FRI';
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-17 12:00:00');

    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-17 12:00:00'))->toBe(0)
        ->and(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-19 10:00:30'))->toBe(1)
        ->and(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 08:15:30'))->toBe(0);
});

it('does not send a catch-up digest when the new slot passed since the last digest', function (): void {
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-22 07:00:00');
    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 07:00:00'))->toBe(0)
        ->and(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 08:15:30'))->toBe(1);

    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-24 12:00:00');
    $this->cron = '0 9 * * TUE';
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-24 12:00:00');

    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-24 12:00:00'))->toBe(0)
        ->and(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-30 09:00:30'))->toBe(1);
});

it('does not send a catch-up digest when the worker has not sent one yet', function (): void {
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-17 09:00:00');
    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-17 09:00:00'))->toBe(0);

    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-18 12:00:00');
    $this->cron = '0 10 * * THU';
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-18 12:00:00');

    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-18 12:00:00'))->toBe(0)
        ->and(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-25 10:00:30'))->toBe(1);
});

it('sends again at the new time when moved later on a day it was already sent', function (): void {
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-22 07:00:00');
    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 07:00:00'))->toBe(0)
        ->and(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 08:15:30'))->toBe(1);

    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-22 09:00:00');
    $this->cron = '0 10 * * MON';
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-22 09:00:00');

    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 09:00:00'))->toBe(0)
        ->and(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 10:00:30'))->toBe(1);
});

it('keeps the schedule when the settings did not change', function (): void {
    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-17 09:00:00'))->toBe(0);

    $this->listener->onWorkerRunning(new WorkerRunningEvent($this->worker, true));

    expect($this->provider->getSchedule()->shouldRestart())->toBeFalse();
});

it('allows a new slot after the last settings read during a long job', function (): void {
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-22 08:10:00');
    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 08:10:00'))->toBe(0);

    $this->cron = '18 8 * * MON';
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-22 08:20:00');

    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 08:20:00'))->toBe(1);
});

it('does not send a catch-up digest after a settings read failure streak', function (): void {
    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-17 09:00:00'))->toBe(0);

    $this->cron = '0 10 * * FRI';
    $this->shouldFail = true;
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-18 12:00:00');
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-19 11:00:00');

    $this->shouldFail = false;
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-19 12:00:00');

    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-19 12:00:00'))->toBe(0)
        ->and(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-26 10:00:30'))->toBe(1);
});

it('rebuilds after a running event inside generator handling without refiring the old slot', function (): void {
    readWeeklyDigestSettingsAt($this->clock, $this->listener, $this->worker, '2026-06-22 07:00:00');
    expect(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 07:00:00'))->toBe(0);
    $this->clock->modify('2026-06-22 08:15:30');
    $sent = 0;
    foreach ($this->generator->getMessages() as $_message) {
        ++$sent;
        $this->cron = '0 10 * * MON';
        $this->listener->onWorkerRunning(new WorkerRunningEvent($this->worker, true));
    }

    expect($sent)->toBe(1)
        ->and(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 08:15:30'))->toBe(0)
        ->and(weeklyDigestsSentAt($this->generator, $this->clock, '2026-06-22 10:00:30'))->toBe(1);
});
