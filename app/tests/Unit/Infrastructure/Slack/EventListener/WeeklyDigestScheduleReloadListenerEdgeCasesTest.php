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

class DigestEdgeRecordingLogger extends NullLogger
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

function digestEdgeListenerSetup(array &$settings, DigestEdgeRecordingLogger $logger, MockClock $clock, array $transports = ['scheduler_weekly_digest' => null]): array
{
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturnUsing(function () use (&$settings): string {
        if ($settings['broken']) {
            throw new RuntimeException('broken');
        }

        return $settings['cron'];
    });
    $facade->allows('weeklyDigestTimezone')->andReturnUsing(function () use (&$settings): string {
        if ($settings['broken']) {
            throw new RuntimeException('broken');
        }

        return $settings['timezone'];
    });

    $receivers = [];
    foreach (array_keys($transports) as $transportName) {
        $receivers[$transportName] = mock(ReceiverInterface::class);
    }

    $provider = new WeeklyDigestScheduleProvider(new DynamicWeeklyDigestTrigger($facade));
    $provider->getSchedule()->setRestart(false);
    $listener = new WeeklyDigestScheduleReloadListener($facade, $provider, $logger, $clock);
    $worker = new Worker($receivers, mock(MessageBusInterface::class));

    return [$listener, $provider, $worker];
}

it('logs one error for a read failure that starts at startup and continues into running events', function (): void {
    $settings = ['cron' => '0 9 * * MON', 'timezone' => 'UTC', 'broken' => true];
    $logger = new DigestEdgeRecordingLogger();
    [$listener, $provider, $worker] = digestEdgeListenerSetup($settings, $logger, new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC'))));

    $listener->onWorkerStarted(new WorkerStartedEvent($worker));
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, false));

    expect($logger->errors)->toBe(['[SLACK][DIGEST-SCHEDULE]: Failed to read the weekly digest settings.'])
        ->and($logger->infos)->toBe([])
        ->and($provider->getSchedule()->shouldRestart())->toBeFalse();
});

it('logs an error again for a new failure streak after the settings read recovered', function (): void {
    $settings = ['cron' => '0 9 * * MON', 'timezone' => 'UTC', 'broken' => true];
    $logger = new DigestEdgeRecordingLogger();
    $clock = new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
    [$listener, $provider, $worker] = digestEdgeListenerSetup($settings, $logger, $clock);

    $listener->onWorkerStarted(new WorkerStartedEvent($worker));
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    $settings['broken'] = false;
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    $settings['broken'] = true;
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($logger->errors)->toHaveCount(2);
});

it('does not rebuild after a failed read when the settings are unchanged', function (): void {
    $settings = ['cron' => '0 9 * * MON', 'timezone' => 'UTC', 'broken' => false];
    $logger = new DigestEdgeRecordingLogger();
    $clock = new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
    [$listener, $provider, $worker] = digestEdgeListenerSetup($settings, $logger, $clock);

    $listener->onWorkerStarted(new WorkerStartedEvent($worker));
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    $settings['broken'] = true;
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    $settings['broken'] = false;
    $clock->modify('2026-06-17 09:05:00');
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($provider->getSchedule()->shouldRestart())->toBeFalse()
        ->and($logger->errors)->toHaveCount(1)
        ->and($logger->infos)->not->toContain('[SLACK][DIGEST-SCHEDULE]: Weekly digest schedule changed to "0 9 * * MON" (UTC), rebuilding it.');
});

it('names the new timezone when only the timezone changes', function (): void {
    $settings = ['cron' => '0 9 * * MON', 'timezone' => 'UTC', 'broken' => false];
    $logger = new DigestEdgeRecordingLogger();
    $clock = new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
    [$listener, $provider, $worker] = digestEdgeListenerSetup($settings, $logger, $clock);

    $listener->onWorkerStarted(new WorkerStartedEvent($worker));
    $settings['timezone'] = 'Europe/Berlin';
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($logger->infos)->toContain('[SLACK][DIGEST-SCHEDULE]: Weekly digest schedule changed to "0 9 * * MON" (Europe/Berlin), rebuilding it.')
        ->and($provider->getSchedule()->shouldRestart())->toBeTrue();
});

it('reads the settings for a worker that consumes the weekly digest transport among others', function (): void {
    $settings = ['cron' => '0 9 * * MON', 'timezone' => 'UTC', 'broken' => false];
    $logger = new DigestEdgeRecordingLogger();
    $clock = new MockClock(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
    [$listener, , $worker] = digestEdgeListenerSetup($settings, $logger, $clock, ['async' => null, 'scheduler_weekly_digest' => null]);

    $listener->onWorkerStarted(new WorkerStartedEvent($worker));

    expect($logger->infos)->toContain('[SLACK][DIGEST-SCHEDULE]: Watching weekly digest schedule "0 9 * * MON" (UTC)');
});
