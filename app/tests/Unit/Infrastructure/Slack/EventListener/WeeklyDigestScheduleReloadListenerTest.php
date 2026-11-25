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

class WeeklyDigestRecordingLogger extends NullLogger
{
    /** @var list<string> */
    public array $infos = [];

    /** @var list<string> */
    public array $errors = [];

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->infos[] = (string) $message;
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->errors[] = (string) $message;
    }
}

function weeklyDigestWorker(array $receivers): Worker
{
    return new Worker($receivers, mock(MessageBusInterface::class));
}

it('ignores workers that do not consume the weekly digest transport', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->expects('weeklyDigestCronExpression')->never();
    $facade->expects('weeklyDigestTimezone')->never();
    $provider = new WeeklyDigestScheduleProvider(mock(DynamicWeeklyDigestTrigger::class));
    $provider->getSchedule()->setRestart(false);
    $listener = new WeeklyDigestScheduleReloadListener($facade, $provider, new NullLogger(), new MockClock());
    $worker = weeklyDigestWorker(['async' => mock(ReceiverInterface::class)]);

    $listener->onWorkerStarted(new WorkerStartedEvent($worker));
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($provider->getSchedule()->shouldRestart())->toBeFalse();
});

it('logs the new schedule when it rebuilds', function (): void {
    $cron = '15 8 * * MON';
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturnUsing(function () use (&$cron): string {
        return $cron;
    });
    $facade->allows('weeklyDigestTimezone')->andReturn('UTC');
    $logger = new WeeklyDigestRecordingLogger();
    $clock = new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
    $provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($facade));
    $listener = new WeeklyDigestScheduleReloadListener($facade, $provider, $logger, $clock);
    $worker = weeklyDigestWorker(['scheduler_weekly_digest' => mock(ReceiverInterface::class)]);
    $listener->onWorkerStarted(new WorkerStartedEvent($worker));
    $provider->getSchedule()->setRestart(false);

    $clock->modify('2026-06-17 10:00:00');
    $cron = '0 10 * * FRI';
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($logger->infos)->toContain('[SLACK][DIGEST-SCHEDULE]: Weekly digest schedule changed to "0 10 * * FRI" (UTC), rebuilding it.');
    expect($provider->getSchedule()->shouldRestart())->toBeTrue();
});

it('rebuilds when only the timezone changes', function (): void {
    $timezone = 'UTC';
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('0 9 * * MON');
    $facade->allows('weeklyDigestTimezone')->andReturnUsing(function () use (&$timezone): string {
        return $timezone;
    });
    $clock = new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
    $provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($facade));
    $provider->getSchedule()->setRestart(false);
    $listener = new WeeklyDigestScheduleReloadListener($facade, $provider, new NullLogger(), $clock);
    $worker = weeklyDigestWorker(['scheduler_weekly_digest' => mock(ReceiverInterface::class)]);
    $listener->onWorkerStarted(new WorkerStartedEvent($worker));
    $provider->getSchedule()->setRestart(false);

    $timezone = 'Europe/Berlin';
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($provider->getSchedule()->shouldRestart())->toBeTrue();
});

it('logs an error once per settings read failure streak and keeps the schedule', function (): void {
    $shouldThrow = false;
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturnUsing(function () use (&$shouldThrow): string {
        if ($shouldThrow) {
            throw new RuntimeException('broken');
        }

        return '0 9 * * MON';
    });
    $facade->allows('weeklyDigestTimezone')->andReturnUsing(function () use (&$shouldThrow): string {
        if ($shouldThrow) {
            throw new RuntimeException('broken');
        }

        return 'UTC';
    });
    $logger = new WeeklyDigestRecordingLogger();
    $clock = new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
    $provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($facade));
    $provider->getSchedule()->setRestart(false);
    $listener = new WeeklyDigestScheduleReloadListener($facade, $provider, $logger, $clock);
    $worker = weeklyDigestWorker(['scheduler_weekly_digest' => mock(ReceiverInterface::class)]);
    $listener->onWorkerStarted(new WorkerStartedEvent($worker));
    $provider->getSchedule()->setRestart(false);

    $shouldThrow = true;
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($logger->errors)->toHaveCount(1)
        ->and($logger->errors[0])->toBe('[SLACK][DIGEST-SCHEDULE]: Failed to read the weekly digest settings.')
        ->and($provider->getSchedule()->shouldRestart())->toBeFalse();
});

it('logs when it starts watching the weekly digest schedule', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('0 9 * * MON');
    $facade->allows('weeklyDigestTimezone')->andReturn('UTC');
    $logger = new WeeklyDigestRecordingLogger();
    $provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($facade));
    $listener = new WeeklyDigestScheduleReloadListener($facade, $provider, $logger, new MockClock());
    $worker = weeklyDigestWorker(['scheduler_weekly_digest' => mock(ReceiverInterface::class)]);

    $listener->onWorkerStarted(new WorkerStartedEvent($worker));

    expect($logger->infos)->toContain('[SLACK][DIGEST-SCHEDULE]: Watching weekly digest schedule "0 9 * * MON" (UTC)');
});

it('treats the first successful read after startup failure as the initial schedule', function (): void {
    $shouldThrow = true;
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturnUsing(function () use (&$shouldThrow): string {
        if ($shouldThrow) {
            throw new RuntimeException('broken');
        }

        return '0 9 * * MON';
    });
    $facade->allows('weeklyDigestTimezone')->andReturnUsing(function () use (&$shouldThrow): string {
        if ($shouldThrow) {
            throw new RuntimeException('broken');
        }

        return 'UTC';
    });
    $logger = new WeeklyDigestRecordingLogger();
    $clock = new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
    $provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($facade));
    $provider->getSchedule()->setRestart(false);
    $listener = new WeeklyDigestScheduleReloadListener($facade, $provider, $logger, $clock);
    $worker = weeklyDigestWorker(['scheduler_weekly_digest' => mock(ReceiverInterface::class)]);

    $listener->onWorkerStarted(new WorkerStartedEvent($worker));
    $provider->getSchedule()->setRestart(false);
    $shouldThrow = false;
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($logger->infos)->toContain('[SLACK][DIGEST-SCHEDULE]: Watching weekly digest schedule "0 9 * * MON" (UTC)')
        ->and($logger->infos)->not->toContain('[SLACK][DIGEST-SCHEDULE]: Weekly digest schedule changed to "0 9 * * MON" (UTC), rebuilding it.')
        ->and($provider->getSchedule()->shouldRestart())->toBeTrue();
});
