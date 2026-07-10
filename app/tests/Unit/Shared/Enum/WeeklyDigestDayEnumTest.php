<?php

declare(strict_types=1);

use App\Shared\Enum\WeeklyDigestDayEnum;

it('maps each day to its cron token', function (): void {
    expect(WeeklyDigestDayEnum::Monday->value)->toBe('MON')
        ->and(WeeklyDigestDayEnum::Sunday->value)->toBe('SUN')
        ->and(WeeklyDigestDayEnum::cases())->toHaveCount(7);
});

it('builds from a stored token', function (): void {
    expect(WeeklyDigestDayEnum::from('FRI'))->toBe(WeeklyDigestDayEnum::Friday);
});
