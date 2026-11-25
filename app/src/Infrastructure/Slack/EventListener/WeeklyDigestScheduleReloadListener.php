<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\EventListener;

use App\Infrastructure\Slack\Schedule\WeeklyDigestScheduleProvider;
use App\Shared\Facade\AppSettingsFacadeInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Event\WorkerStartedEvent;

#[AsEventListener(event: WorkerStartedEvent::class, method: 'onWorkerStarted')]
#[AsEventListener(event: WorkerRunningEvent::class, method: 'onWorkerRunning')]
class WeeklyDigestScheduleReloadListener
{
    private const string TRANSPORT_NAME = 'scheduler_weekly_digest';

    private bool $enabled = false;
    private ?string $fingerprint = null;
    private ?\DateTimeImmutable $lastReadAt = null;
    private bool $failureLogged = false;

    public function __construct(
        private readonly AppSettingsFacadeInterface $appSettingsFacade,
        private readonly WeeklyDigestScheduleProvider $scheduleProvider,
        private readonly LoggerInterface $logger,
        private readonly ClockInterface $clock = new Clock(),
    ) {
    }

    public function onWorkerStarted(WorkerStartedEvent $event): void
    {
        if (!in_array(self::TRANSPORT_NAME, $event->getWorker()->getMetadata()->getTransportNames(), true)) {
            return;
        }

        $this->enabled = true;

        try {
            [$cron, $timezone, $fingerprint] = $this->readSettings();
            $this->fingerprint = $fingerprint;
            $this->lastReadAt = $this->clock->now();
            $this->failureLogged = false;
            $this->logger->info(sprintf('[SLACK][DIGEST-SCHEDULE]: Watching weekly digest schedule "%s" (%s)', $cron, $timezone));
        } catch (\Throwable $exception) {
            $this->recordReadFailure($exception);
        }
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            [$cron, $timezone, $fingerprint] = $this->readSettings();
        } catch (\Throwable $exception) {
            $this->recordReadFailure($exception);

            return;
        }

        $this->failureLogged = false;
        $now = $this->clock->now();

        if (null === $this->fingerprint) {
            $this->fingerprint = $fingerprint;
            $this->lastReadAt = $now;
            $this->scheduleProvider->reload($now);
            $this->logger->info(sprintf('[SLACK][DIGEST-SCHEDULE]: Watching weekly digest schedule "%s" (%s)', $cron, $timezone));

            return;
        }

        if ($fingerprint === $this->fingerprint) {
            $this->lastReadAt = $now;

            return;
        }

        $notBefore = $this->lastReadAt ?? $now;
        $this->fingerprint = $fingerprint;
        $this->lastReadAt = $now;
        $this->scheduleProvider->reload($notBefore);
        $this->logger->info(sprintf('[SLACK][DIGEST-SCHEDULE]: Weekly digest schedule changed to "%s" (%s), rebuilding it.', $cron, $timezone));
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function readSettings(): array
    {
        $cron = $this->appSettingsFacade->weeklyDigestCronExpression();
        $timezone = $this->appSettingsFacade->weeklyDigestTimezone();

        return [$cron, $timezone, sprintf('%s %s', $cron, $timezone)];
    }

    private function recordReadFailure(\Throwable $exception): void
    {
        $this->lastReadAt = null;

        if ($this->failureLogged) {
            return;
        }

        $this->failureLogged = true;
        $this->logger->error('[SLACK][DIGEST-SCHEDULE]: Failed to read the weekly digest settings.', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
