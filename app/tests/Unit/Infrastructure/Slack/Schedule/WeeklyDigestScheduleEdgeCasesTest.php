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
    $this->cron = '0 9 * * MON';
    $this->timezone = 'UTC';
    $this->shouldFail = false;
    $this->clock = new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
    $this->facade = mock(AppSettingsFacadeInterface::class);
    $this->facade->allows('weeklyDigestCronExpression')->andReturnUsing(function (): string {
        if ($this->shouldFail) {
            throw new RuntimeException('broken');
        }

        return $this->cron;
    });
    $this->facade->allows('weeklyDigestTimezone')->andReturnUsing(function (): string {
        if ($this->shouldFail) {
            throw new RuntimeException('broken');
        }

        return $this->timezone;
    });
    $this->provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($this->facade));
    $this->generator = new MessageGenerator($this->provider, 'weekly_digest', $this->clock);
    $this->listener = new WeeklyDigestScheduleReloadListener($this->facade, $this->provider, new NullLogger(), $this->clock);
    $this->worker = new Worker(['scheduler_weekly_digest' => mock(ReceiverInterface::class)], mock(MessageBusInterface::class));
    $this->listener->onWorkerStarted(new WorkerStartedEvent($this->worker));
});

function digestEdgeSentAt(MessageGenerator $generator, MockClock $clock, string $at): int
{
    $clock->modify($at);

    return iterator_count($generator->getMessages());
}

function digestEdgeReadAt(WeeklyDigestScheduleReloadListener $listener, Worker $worker, MockClock $clock, string $at): void
{
    $clock->modify($at);
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
}

it('moves a timezone-only change to the same wall clock time in the new zone', function (): void {
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-17 09:00:00'))->toBe(0);

    $this->timezone = 'Europe/Berlin';
    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-22 06:30:00');

    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-22 06:30:00'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-22 07:00:30'))->toBe(1)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-22 09:00:30'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-29 07:00:30'))->toBe(1);
});

it('applies only the last of several changes saved before the next loop', function (): void {
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-17 09:00:00'))->toBe(0);

    $this->cron = '0 10 * * FRI';
    $this->cron = '30 12 * * FRI';
    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-17 12:00:00');

    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-17 12:00:00'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 10:00:30'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 12:30:30'))->toBe(1)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-26 12:30:30'))->toBe(1);
});

it('uses the latest unchanged read as the floor instead of the first read', function (): void {
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-17 09:00:00'))->toBe(0);

    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-18 09:00:00');
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-18 09:00:00'))->toBe(0);

    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-19 09:00:00');
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 09:00:00'))->toBe(0);

    $this->cron = '0 10 * * THU';
    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-19 12:00:00');

    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 12:00:30'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-25 10:00:30'))->toBe(1);
});

it('sends at the configured slot after a change back to the original value and not at the intermediate one', function (): void {
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-17 09:00:00'))->toBe(0);

    $this->cron = '0 10 * * FRI';
    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-18 12:00:00');
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-18 12:00:30'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 10:00:30'))->toBe(1);

    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-19 10:30:00');
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 10:30:30'))->toBe(0);

    $this->cron = '0 9 * * MON';
    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-19 11:00:00');

    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 11:00:30'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-22 09:00:30'))->toBe(1)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-26 10:00:30'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-29 09:00:30'))->toBe(1);
});

it('uses the first good read after a failed start read as the floor for a later change', function (): void {
    $clock = new MockClock(new DateTimeImmutable('2026-06-17 08:00:00', new DateTimeZone('UTC')));
    $this->shouldFail = true;
    $provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($this->facade));
    $generator = new MessageGenerator($provider, 'weekly_digest', $clock);
    $listener = new WeeklyDigestScheduleReloadListener($this->facade, $provider, new NullLogger(), $clock);
    $worker = new Worker(['scheduler_weekly_digest' => mock(ReceiverInterface::class)], mock(MessageBusInterface::class));
    $listener->onWorkerStarted(new WorkerStartedEvent($worker));

    $this->shouldFail = false;
    $this->cron = '15 8 * * MON';
    digestEdgeReadAt($listener, $worker, $clock, '2026-06-17 09:00:00');
    expect(digestEdgeSentAt($generator, $clock, '2026-06-17 09:00:00'))->toBe(0);

    $this->cron = '30 10 * * THU';
    digestEdgeReadAt($listener, $worker, $clock, '2026-06-18 11:00:00');

    expect(digestEdgeSentAt($generator, $clock, '2026-06-18 11:00:30'))->toBe(1)
        ->and(digestEdgeSentAt($generator, $clock, '2026-06-25 10:30:30'))->toBe(1);
});

it('rebuilds again for a second change made after the first rebuild', function (): void {
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-17 09:00:00'))->toBe(0);

    $this->cron = '0 10 * * FRI';
    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-18 12:00:00');
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-18 12:00:30'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 10:00:30'))->toBe(1);

    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-19 10:30:00');
    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 10:30:30'))->toBe(0);

    $this->cron = '30 12 * * FRI';
    digestEdgeReadAt($this->listener, $this->worker, $this->clock, '2026-06-19 11:00:00');

    expect(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 11:00:30'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-19 12:30:30'))->toBe(1)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-26 10:00:30'))->toBe(0)
        ->and(digestEdgeSentAt($this->generator, $this->clock, '2026-06-26 12:30:30'))->toBe(1);
});

it('sends exactly one digest when the configured Berlin slot repeats during fall-back', function (): void {
    $this->cron = '30 2 * * SUN';
    $this->timezone = 'Europe/Berlin';
    $this->clock = new MockClock(new DateTimeImmutable('2026-10-24 12:00:00', new DateTimeZone('UTC')));
    $provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($this->facade));
    $generator = new MessageGenerator($provider, 'weekly_digest', $this->clock);

    expect(digestEdgeSentAt($generator, $this->clock, '2026-10-24 12:00:00'))->toBe(0)
        ->and(digestEdgeSentAt($generator, $this->clock, '2026-10-25 00:30:30'))->toBe(1)
        ->and(digestEdgeSentAt($generator, $this->clock, '2026-10-25 01:30:30'))->toBe(0)
        ->and(digestEdgeSentAt($generator, $this->clock, '2026-11-01 01:30:30'))->toBe(1);
});

it('sends exactly one digest when the configured Havana slot repeats at midnight during fall-back', function (): void {
    $this->cron = '15 0 * * SUN';
    $this->timezone = 'America/Havana';
    $this->clock = new MockClock(new DateTimeImmutable('2026-10-31 12:00:00', new DateTimeZone('UTC')));
    $provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($this->facade));
    $generator = new MessageGenerator($provider, 'weekly_digest', $this->clock);

    expect(digestEdgeSentAt($generator, $this->clock, '2026-10-31 12:00:00'))->toBe(0)
        ->and(digestEdgeSentAt($generator, $this->clock, '2026-11-01 04:15:30'))->toBe(1)
        ->and(digestEdgeSentAt($generator, $this->clock, '2026-11-01 05:15:30'))->toBe(0)
        ->and(digestEdgeSentAt($generator, $this->clock, '2026-11-08 05:15:30'))->toBe(1);
});

it('sends the Berlin 03:30 digest once across fall-back', function (): void {
    $this->cron = '30 3 * * SUN';
    $this->timezone = 'Europe/Berlin';
    $this->clock = new MockClock(new DateTimeImmutable('2026-10-24 12:00:00', new DateTimeZone('UTC')));
    $provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($this->facade));
    $generator = new MessageGenerator($provider, 'weekly_digest', $this->clock);

    expect(digestEdgeSentAt($generator, $this->clock, '2026-10-24 12:00:00'))->toBe(0)
        ->and(digestEdgeSentAt($generator, $this->clock, '2026-10-25 01:30:30'))->toBe(0)
        ->and(digestEdgeSentAt($generator, $this->clock, '2026-10-25 02:30:30'))->toBe(1)
        ->and(digestEdgeSentAt($generator, $this->clock, '2026-11-01 02:30:30'))->toBe(1);
});
