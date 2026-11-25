<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Schedule\DynamicWeeklyDigestTrigger;
use App\Shared\Facade\AppSettingsFacadeInterface;

function timezoneTriggerNextRunInUtc(string $cron, string $timezone, string $run, ?string $notBefore = null): string
{
    $facade = mock(AppSettingsFacadeInterface::class);
    $facade->allows('weeklyDigestCronExpression')->andReturn($cron);
    $facade->allows('weeklyDigestTimezone')->andReturn($timezone);

    $trigger = new DynamicWeeklyDigestTrigger($facade);
    if (null !== $notBefore) {
        $trigger->setNotBefore(new DateTimeImmutable($notBefore, new DateTimeZone('UTC')));
    }

    return $trigger->getNextRunDate(new DateTimeImmutable($run, new DateTimeZone('UTC')))
        ->setTimezone(new DateTimeZone('UTC'))
        ->format('Y-m-d H:i:s');
}

it('keeps the local wall clock slot across the spring daylight saving change', function (): void {
    expect(timezoneTriggerNextRunInUtc('0 9 * * MON', 'Europe/Berlin', '2026-03-22 12:00:00'))->toBe('2026-03-23 08:00:00')
        ->and(timezoneTriggerNextRunInUtc('0 9 * * MON', 'Europe/Berlin', '2026-03-29 12:00:00'))->toBe('2026-03-30 07:00:00');
});

it('keeps the local wall clock slot across the autumn daylight saving change', function (): void {
    expect(timezoneTriggerNextRunInUtc('0 9 * * MON', 'Europe/Berlin', '2026-10-25 12:00:00'))->toBe('2026-10-26 08:00:00');
});

it('fires the Monday midnight slot in a timezone ahead of UTC when the floor is still on the Sunday in UTC', function (): void {
    expect(timezoneTriggerNextRunInUtc('0 0 * * MON', 'Asia/Tokyo', '2026-06-21 15:30:00', '2026-06-28 14:59:30'))->toBe('2026-06-28 15:00:00');
});

it('does not fire the Monday midnight slot again when the floor is just after it', function (): void {
    expect(timezoneTriggerNextRunInUtc('0 0 * * MON', 'Asia/Tokyo', '2026-06-21 15:30:00', '2026-06-28 15:00:30'))->toBe('2026-07-05 15:00:00');
});
