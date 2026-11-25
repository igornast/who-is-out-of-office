<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Schedule;

use App\Shared\Facade\AppSettingsFacadeInterface;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class DynamicWeeklyDigestTrigger implements TriggerInterface
{
    private ?\DateTimeImmutable $notBefore = null;

    public function __construct(private readonly AppSettingsFacadeInterface $appSettingsFacade)
    {
    }

    public function setNotBefore(?\DateTimeImmutable $notBefore): void
    {
        $this->notBefore = $notBefore;
    }

    public function __toString(): string
    {
        return 'weekly_digest';
    }

    public function getNextRunDate(\DateTimeImmutable $run): ?\DateTimeImmutable
    {
        $expression = $this->appSettingsFacade->weeklyDigestCronExpression();
        $timezone = $this->appSettingsFacade->weeklyDigestTimezone();

        $from = null !== $this->notBefore && $this->notBefore > $run ? $this->notBefore : $run;
        $configuredTimezone = new \DateTimeZone($timezone);
        [$minute, $hour, $dayOfWeek] = $this->parseCronExpression($expression);
        $utcTimezone = new \DateTimeZone('UTC');
        $localDate = $from->setTimezone($configuredTimezone)->format('Y-m-d');
        $date = new \DateTimeImmutable($localDate.' 00:00:00', $utcTimezone);

        $dayOffset = ($dayOfWeek - (int) $date->format('N') + 7) % 7;
        $candidateDate = $date->modify(sprintf('+%d days', $dayOffset));
        $candidate = $this->resolveLocalSlot($candidateDate->format('Y-m-d'), $hour, $minute, $configuredTimezone);

        if ($candidate > $from) {
            return $candidate;
        }

        return $this->resolveLocalSlot(
            $candidateDate->modify('+7 days')->format('Y-m-d'),
            $hour,
            $minute,
            $configuredTimezone,
        );
    }

    private function resolveLocalSlot(string $localDate, int $hour, int $minute, \DateTimeZone $timezone): \DateTimeImmutable
    {
        $utcTimezone = new \DateTimeZone('UTC');
        $target = sprintf('%s %02d:%02d', $localDate, $hour, $minute);
        $naiveTimestamp = new \DateTimeImmutable($target.':00', $utcTimezone)->getTimestamp();
        $transitions = $timezone->getTransitions($naiveTimestamp - 172800, $naiveTimestamp + 172800);

        $offsets = [];
        foreach ($transitions as $transition) {
            $offsets[$transition['offset']] = true;
        }

        $candidateTimestamp = null;
        foreach (array_keys($offsets) as $offset) {
            $timestamp = $naiveTimestamp - (int) $offset;
            $candidate = new \DateTimeImmutable('@'.$timestamp);
            if ($candidate->setTimezone($timezone)->format('Y-m-d H:i') !== $target) {
                continue;
            }

            if (null === $candidateTimestamp || $timestamp < $candidateTimestamp) {
                $candidateTimestamp = $timestamp;
            }
        }

        if (null !== $candidateTimestamp) {
            return new \DateTimeImmutable('@'.$candidateTimestamp);
        }

        $largestOffset = max(array_keys($offsets) ?: [PHP_INT_MIN]);
        $beforeTransition = new \DateTimeImmutable('@'.($naiveTimestamp - (int) $largestOffset));

        return new \DateTimeImmutable('@'.($naiveTimestamp - $timezone->getOffset($beforeTransition)));
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function parseCronExpression(string $expression): array
    {
        if (!preg_match('/^(\d{1,2}) (\d{1,2}) \* \* ([A-Z]{3})$/', $expression, $matches)) {
            throw new \InvalidArgumentException(sprintf('Unsupported weekly digest cron expression: %s', $expression));
        }

        $minute = (int) $matches[1];
        $hour = (int) $matches[2];
        $dayOfWeek = [
            'MON' => 1,
            'TUE' => 2,
            'WED' => 3,
            'THU' => 4,
            'FRI' => 5,
            'SAT' => 6,
            'SUN' => 7,
        ][$matches[3]] ?? null;

        if ($minute > 59 || $hour > 23 || null === $dayOfWeek) {
            throw new \InvalidArgumentException(sprintf('Unsupported weekly digest cron expression: %s', $expression));
        }

        return [$minute, $hour, $dayOfWeek];
    }
}
