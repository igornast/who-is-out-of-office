<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Schedule\DynamicWeeklyDigestTrigger;
use App\Shared\Facade\AppSettingsFacadeInterface;

it('identifies itself as the weekly_digest schedule', function (): void {
    $trigger = new DynamicWeeklyDigestTrigger(mock(AppSettingsFacadeInterface::class));

    expect((string) $trigger)->toBe('weekly_digest');
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

it('reflects a changed setting on a subsequent call', function (): void {
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestTimezone')->andReturn('UTC');
    $facade->expects('weeklyDigestCronExpression')->twice()->andReturn('15 8 * * MON', '0 10 * * FRI');

    $trigger = new DynamicWeeklyDigestTrigger($facade);
    $now = new DateTimeImmutable('2026-06-17 09:00:00', new DateTimeZone('UTC'));

    expect($trigger->getNextRunDate($now)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))->toBe('2026-06-22 08:15')
        ->and($trigger->getNextRunDate($now)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'))->toBe('2026-06-19 10:00');
});
