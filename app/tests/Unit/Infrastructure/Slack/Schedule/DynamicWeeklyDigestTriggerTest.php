<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Schedule\DynamicWeeklyDigestTrigger;
use App\Shared\Facade\AppSettingsFacadeInterface;

it('identifies itself as the weekly_digest schedule', function (): void {
    $trigger = new DynamicWeeklyDigestTrigger(mock(AppSettingsFacadeInterface::class));

    expect((string) $trigger)->toBe('weekly_digest');
});

it('never returns a run date before the reload floor', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('0 9 * * TUE');
    $facade->allows('weeklyDigestTimezone')->andReturn('UTC');
    $trigger = new DynamicWeeklyDigestTrigger($facade);
    $trigger->setNotBefore(new DateTimeImmutable('2026-06-24 12:00:00', new DateTimeZone('UTC')));

    $next = $trigger->getNextRunDate(new DateTimeImmutable('2026-06-22 08:15:00', new DateTimeZone('UTC')));

    expect($next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))->toBe('2026-06-30 09:00');
});

it('computes the next run from the configured weekly cron in UTC', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('15 8 * * MON');
    $facade->allows('weeklyDigestTimezone')->andReturn('UTC');

    $trigger = new DynamicWeeklyDigestTrigger($facade);
    $now = new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC'));

    $next = $trigger->getNextRunDate($now);

    expect($next)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))->toBe('2026-06-22 08:15');
});

it('respects the configured timezone', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('0 9 * * MON');
    $facade->allows('weeklyDigestTimezone')->andReturn('Europe/Berlin');

    $trigger = new DynamicWeeklyDigestTrigger($facade);
    $now = new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC'));

    $next = $trigger->getNextRunDate($now);

    expect($next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))->toBe('2026-06-22 07:00');
});

it('reads the settings on every call', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestTimezone')->andReturn('UTC');
    $facade->expects('weeklyDigestCronExpression')->twice()->andReturn('15 8 * * MON', '0 10 * * FRI');

    $trigger = new DynamicWeeklyDigestTrigger($facade);
    $now = new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC'));

    expect($trigger->getNextRunDate($now)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))->toBe('2026-06-22 08:15')
        ->and($trigger->getNextRunDate($now)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))->toBe('2026-06-19 10:00');
});

it('does not schedule the repeated Berlin slot a second time on the autumn fall-back day', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('30 2 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('Europe/Berlin');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-24 12:00:00', new DateTimeZone('UTC')));
    $second = $trigger->getNextRunDate($first);

    expect($first->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-10-25 00:30:00')
        ->and($second->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-01 01:30:00');
});

it('does not schedule the repeated New York slot a second time on its autumn fall-back day', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('30 1 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('America/New_York');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-31 12:00:00', new DateTimeZone('UTC')));
    $second = $trigger->getNextRunDate($first);

    expect($first->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-01 05:30:00')
        ->and($second->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-08 06:30:00');
});

it('keeps the Berlin 03:30 fall-back slot to one occurrence', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('30 3 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('Europe/Berlin');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-24 12:00:00', new DateTimeZone('UTC')));
    $second = $trigger->getNextRunDate($first);

    expect($first->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-10-25 02:30:00')
        ->and($second->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-01 02:30:00');
});

it('fires the ambiguous Berlin 02:00 slot once before advancing to the next week', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('0 2 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('Europe/Berlin');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-24 12:00:00', new DateTimeZone('UTC')));
    $second = $trigger->getNextRunDate($first);

    expect($first->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-10-25 00:00:00')
        ->and($second->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-01 01:00:00');
});

it('fires the ambiguous New York 01:30 slot once before advancing to the next week', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('30 1 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('America/New_York');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-31 12:00:00', new DateTimeZone('UTC')));
    $second = $trigger->getNextRunDate($first);

    expect($first->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-01 05:30:00')
        ->and($second->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-08 06:30:00');
});

it('fires the Dublin negative-DST slot at its first occurrence', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('30 1 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('Europe/Dublin');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-24 12:00:00', new DateTimeZone('UTC')));
    $second = $trigger->getNextRunDate($first);

    expect($first->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-10-25 00:30:00')
        ->and($second->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-01 01:30:00');
});

it('fires the Casablanca slot at the first occurrence around its February transition', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('30 2 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('Africa/Casablanca');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $transition = (new DateTimeZone('Africa/Casablanca'))->getTransitions(
        (new DateTimeImmutable('2026-02-13 00:00:00', new DateTimeZone('UTC')))->getTimestamp(),
        (new DateTimeImmutable('2026-02-17 00:00:00', new DateTimeZone('UTC')))->getTimestamp(),
    );
    expect($transition)->not->toBeFalse();

    $next = $trigger->getNextRunDate(new DateTimeImmutable('2026-02-14 12:00:00', new DateTimeZone('UTC')));

    expect($next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-02-15 01:30:00');
});

it('skips the duplicate Havana slot when the reload floor is inside the repeated hour', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('15 0 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('America/Havana');
    $trigger = new DynamicWeeklyDigestTrigger($facade);
    $trigger->setNotBefore(new DateTimeImmutable('2026-11-01 05:05:00', new DateTimeZone('UTC')));

    $next = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-31 12:00:00', new DateTimeZone('UTC')));

    expect($next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-08 05:15:00');
});

it('fires the Havana fall-back slot once from the preceding week', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('15 0 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('America/Havana');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $next = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-31 12:00:00', new DateTimeZone('UTC')));

    expect($next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-01 04:15:00');
});

it('resolves both Lord Howe half-hour transitions without duplicate or skipped slots', function (): void {
    $fallBackFacade = mock(AppSettingsFacadeInterface::class);
    $fallBackFacade->allows('weeklyDigestCronExpression')->andReturn('30 1 * * SUN');
    $fallBackFacade->allows('weeklyDigestTimezone')->andReturn('Australia/Lord_Howe');
    $fallBack = new DynamicWeeklyDigestTrigger($fallBackFacade)->getNextRunDate(new DateTimeImmutable('2026-04-04 12:00:00', new DateTimeZone('UTC')));

    $springForwardFacade = mock(AppSettingsFacadeInterface::class);
    $springForwardFacade->allows('weeklyDigestCronExpression')->andReturn('15 2 * * SUN');
    $springForwardFacade->allows('weeklyDigestTimezone')->andReturn('Australia/Lord_Howe');
    $springForward = new DynamicWeeklyDigestTrigger($springForwardFacade)->getNextRunDate(new DateTimeImmutable('2026-10-03 12:00:00', new DateTimeZone('UTC')));

    expect($fallBack->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-04-04 14:30:00')
        ->and($springForward->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-10-03 15:45:00');
});

it('advances past the Berlin repeated hour when from is inside it', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('30 2 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('Europe/Berlin');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $next = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-25 01:00:00', new DateTimeZone('UTC')));

    expect($next->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-01 01:30:00');
});

it('matches a brute-force minute scan around every 2026 transition in representative zones', function (): void {
    foreach (['Europe/Berlin', 'America/New_York', 'Europe/Dublin', 'America/Havana', 'Australia/Lord_Howe', 'Africa/Casablanca'] as $timezone) {
        $zone = new DateTimeZone($timezone);
        $transitions = $zone->getTransitions(
            (new DateTimeImmutable('2026-01-01 00:00:00', new DateTimeZone('UTC')))->getTimestamp(),
            (new DateTimeImmutable('2027-01-01 00:00:00', new DateTimeZone('UTC')))->getTimestamp(),
        );

        expect($transitions)->not->toBeFalse();
        for ($index = 1, $count = count($transitions); $index < $count; ++$index) {
            $beforeOffset = $transitions[$index - 1]['offset'];
            $afterOffset = $transitions[$index]['offset'];
            $transitionTimestamp = $transitions[$index]['ts'];
            $naiveSlotTimestamp = $transitionTimestamp + $beforeOffset + intdiv($afterOffset - $beforeOffset, 2);
            $slot = new DateTimeImmutable('@'.$naiveSlotTimestamp);
            $slotDate = $slot->setTimezone(new DateTimeZone('UTC'));
            $dayOfWeek = $slotDate->format('D');
            $hour = (int) $slotDate->format('G');
            $minute = (int) $slotDate->format('i');
            $cron = sprintf('%d %d * * %s', $minute, $hour, strtoupper($dayOfWeek));
            $from = (new DateTimeImmutable('@'.($transitionTimestamp - 172800)))->setTimezone(new DateTimeZone('UTC'));

            $facade = mock(AppSettingsFacadeInterface::class);
            $facade->allows('weeklyDigestCronExpression')->andReturn($cron);
            $facade->allows('weeklyDigestTimezone')->andReturn($timezone);
            $trigger = new DynamicWeeklyDigestTrigger($facade);

            $actual = $trigger->getNextRunDate($from);
            $expected = bruteForceWeeklySlot($from, $hour, $minute, $dayOfWeek, $zone);

            expect($actual->getTimestamp())->toBe($expected->getTimestamp(), $timezone.' transition '.$transitions[$index]['time']);
        }
    }
});

function bruteForceWeeklySlot(DateTimeImmutable $from, int $hour, int $minute, string $dayOfWeek, DateTimeZone $timezone): DateTimeImmutable
{
    $start = $from->getTimestamp() - 172800;
    $end = $from->getTimestamp() + 604800;
    $localStart = new DateTimeImmutable($from->setTimezone($timezone)->format('Y-m-d').' 00:00:00', new DateTimeZone('UTC'));
    $targetDate = null;
    for ($dayOffset = 0; $dayOffset <= 7; ++$dayOffset) {
        $date = $localStart->modify(sprintf('+%d days', $dayOffset));
        if ($date->format('D') === $dayOfWeek) {
            $targetDate = $date->format('Y-m-d');
            break;
        }
    }

    if (null === $targetDate) {
        throw new RuntimeException(sprintf('No target date found for %s', $dayOfWeek));
    }

    $target = sprintf('%s %02d:%02d', $targetDate, $hour, $minute);

    for ($timestamp = $start; $timestamp <= $end; $timestamp += 60) {
        $candidate = (new DateTimeImmutable('@'.$timestamp))->setTimezone($timezone);
        if ($candidate > $from && $candidate->format('D') === $dayOfWeek && $candidate->format('Y-m-d H:i') === $target) {
            return $candidate->setTimezone(new DateTimeZone('UTC'));
        }
    }

    $naive = new DateTimeImmutable($target.':00', new DateTimeZone('UTC'));
    $transitions = $timezone->getTransitions($naive->getTimestamp() - 172800, $naive->getTimestamp() + 172800);
    for ($index = 1, $count = count($transitions); $index < $count; ++$index) {
        $beforeOffset = $transitions[$index - 1]['offset'];
        $afterOffset = $transitions[$index]['offset'];
        if ($afterOffset <= $beforeOffset) {
            continue;
        }

        $transitionTimestamp = $transitions[$index]['ts'];
        $gapStart = $transitionTimestamp + $beforeOffset;
        $gapEnd = $transitionTimestamp + $afterOffset;
        if ($naive->getTimestamp() >= $gapStart && $naive->getTimestamp() < $gapEnd) {
            return new DateTimeImmutable('@'.($naive->getTimestamp() - $beforeOffset));
        }
    }

    throw new RuntimeException(sprintf('No brute-force weekly slot found for %s %s %s', $timezone->getName(), $targetDate, $target));
}

it('fires the New York 02:30 slot once after the autumn fall-back transition', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('30 2 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('America/New_York');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-10-31 12:00:00', new DateTimeZone('UTC')));
    $second = $trigger->getNextRunDate($first);

    expect($first->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-01 07:30:00')
        ->and($second->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-11-08 07:30:00');
});

it('fires the non-existent Berlin spring-forward slot once at the next valid local time', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('30 2 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('Europe/Berlin');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-03-28 12:00:00', new DateTimeZone('UTC')));
    $second = $trigger->getNextRunDate($first);

    expect($first->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-03-29 01:30:00')
        ->and($second->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-04-05 00:30:00');
});

it('fires the non-existent New York spring-forward slot once at the next valid local time', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn('30 2 * * SUN');
    $facade->allows('weeklyDigestTimezone')->andReturn('America/New_York');
    $trigger = new DynamicWeeklyDigestTrigger($facade);

    $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-03-07 12:00:00', new DateTimeZone('UTC')));
    $second = $trigger->getNextRunDate($first);

    expect($first->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-03-08 07:30:00')
        ->and($second->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-03-15 06:30:00');
});

it('keeps normal weekly slots unchanged in UTC and Asia/Tokyo', function (): void {
    $cases = [
        ['UTC', '2026-06-22 08:15:00', '2026-06-29 08:15:00'],
        ['Asia/Tokyo', '2026-06-21 23:15:00', '2026-06-28 23:15:00'],
    ];

    foreach ($cases as [$timezone, $expectedFirst, $expectedSecond]) {
        $facade = mock(AppSettingsFacadeInterface::class);
        $facade->allows('weeklyDigestCronExpression')->andReturn('15 8 * * MON');
        $facade->allows('weeklyDigestTimezone')->andReturn($timezone);
        $trigger = new DynamicWeeklyDigestTrigger($facade);

        $first = $trigger->getNextRunDate(new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC')));
        $second = $trigger->getNextRunDate($first);

        expect($first->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe($expectedFirst)
            ->and($second->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe($expectedSecond);
    }
});
