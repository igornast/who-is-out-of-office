<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Schedule;

use App\Shared\Facade\AppSettingsFacadeInterface;
use Cron\CronExpression;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class DynamicWeeklyDigestTrigger implements TriggerInterface
{
    public function __construct(private readonly AppSettingsFacadeInterface $appSettingsFacade)
    {
    }

    public function __toString(): string
    {
        return 'weekly_digest';
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        $expression = $this->appSettingsFacade->weeklyDigestCronExpression();
        $timezone = $this->appSettingsFacade->weeklyDigestTimezone();

        $next = new CronExpression($expression)->getNextRunDate($run, 0, false, $timezone);

        return \DateTimeImmutable::createFromInterface($next);
    }
}
