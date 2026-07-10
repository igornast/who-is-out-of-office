<?php

declare(strict_types=1);

use App\Shared\DTO\Settings\AppSettingsDTO;
use App\Shared\Enum\WeeklyDigestDayEnum;
use App\Tests\_fixtures\Shared\DTO\Settings\AppSettingsDTOFixture;
use Symfony\Component\Validator\Validation;

it('creates from array with all keys present', function (): void {
    $data = [
        'leave_request' => [
            'auto_approve' => true,
            'auto_approve_delay' => 60,
            'default_annual_allowance' => 25,
            'min_notice_days' => 3,
            'max_consecutive_days' => 10,
        ],
        'notification' => [
            'skip_weekend_holidays' => true,
        ],
        'slack' => [
            'status_sync_enabled' => true,
        ],
        'organization' => [
            'name' => 'Acme Inc.',
        ],
    ];

    $dto = AppSettingsDTO::fromArray($data);

    expect($dto->autoApprove)->toBeTrue()
        ->and($dto->autoApproveDelay)->toBe(60)
        ->and($dto->defaultAnnualAllowance)->toBe(25)
        ->and($dto->minNoticeDays)->toBe(3)
        ->and($dto->maxConsecutiveDays)->toBe(10)
        ->and($dto->skipWeekendHolidays)->toBeTrue()
        ->and($dto->slackStatusSyncEnabled)->toBeTrue()
        ->and($dto->organizationName)->toBe('Acme Inc.');
});

it('falls back to false when slack.status_sync_enabled is missing (backward compatible)', function (): void {
    $data = [
        'leave_request' => [
            'auto_approve' => false,
            'auto_approve_delay' => 0,
            'default_annual_allowance' => 24,
            'min_notice_days' => 0,
            'max_consecutive_days' => 0,
        ],
        'notification' => [
            'skip_weekend_holidays' => false,
        ],
    ];

    $dto = AppSettingsDTO::fromArray($data);

    expect($dto->slackStatusSyncEnabled)->toBeFalse()
        ->and($dto->skipWeekendHolidays)->toBeFalse()
        ->and($dto->organizationName)->toBe('');
});

it('round-trips through toArray and fromArray', function (): void {
    $dto = new AppSettingsDTO(
        autoApprove: true,
        autoApproveDelay: 120,
        defaultAnnualAllowance: 30,
        minNoticeDays: 5,
        maxConsecutiveDays: 15,
        skipWeekendHolidays: true,
        slackStatusSyncEnabled: true,
        organizationName: 'Acme Inc.',
    );

    $restored = AppSettingsDTO::fromArray($dto->toArray());

    expect($restored->autoApprove)->toBe($dto->autoApprove)
        ->and($restored->autoApproveDelay)->toBe($dto->autoApproveDelay)
        ->and($restored->defaultAnnualAllowance)->toBe($dto->defaultAnnualAllowance)
        ->and($restored->minNoticeDays)->toBe($dto->minNoticeDays)
        ->and($restored->maxConsecutiveDays)->toBe($dto->maxConsecutiveDays)
        ->and($restored->skipWeekendHolidays)->toBe($dto->skipWeekendHolidays)
        ->and($restored->slackStatusSyncEnabled)->toBe($dto->slackStatusSyncEnabled)
        ->and($restored->organizationName)->toBe($dto->organizationName);
});

it('serializes slackStatusSyncEnabled to the correct nested key', function (): void {
    $dto = new AppSettingsDTO(
        autoApprove: false,
        autoApproveDelay: 0,
        defaultAnnualAllowance: 24,
        minNoticeDays: 0,
        maxConsecutiveDays: 0,
        skipWeekendHolidays: false,
        slackStatusSyncEnabled: true,
        organizationName: 'Acme Inc.',
    );

    $array = $dto->toArray();

    expect($array['slack']['status_sync_enabled'])->toBeTrue();
});

it('round-trips weekly digest schedule fields through fromArray/toArray', function (): void {
    $data = [
        'slack' => [
            'weekly_digest_day' => 'WED',
            'weekly_digest_time' => '09:30',
            'weekly_digest_timezone' => 'Europe/Berlin',
        ],
    ];

    $dto = AppSettingsDTO::fromArray($data);

    expect($dto->weeklyDigestTime)->toBe('09:30')
        ->and($dto->weeklyDigestTimezone)->toBe('Europe/Berlin')
        ->and($dto->weeklyDigestDay)->toBe(WeeklyDigestDayEnum::Wednesday);

    $out = $dto->toArray();
    expect($out['slack']['weekly_digest_day'])->toBe('WED')
        ->and($out['slack']['weekly_digest_time'])->toBe('09:30')
        ->and($out['slack']['weekly_digest_timezone'])->toBe('Europe/Berlin');
});

it('defaults weekly digest fields when keys are absent', function (): void {
    $dto = AppSettingsDTO::fromArray([]);

    expect($dto->weeklyDigestDay)->toBe(WeeklyDigestDayEnum::Monday)
        ->and($dto->weeklyDigestTime)->toBe('08:15')
        ->and($dto->weeklyDigestTimezone)->toBe('UTC');
});

it('rejects an invalid digest time and timezone', function (): void {
    $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

    $dto = AppSettingsDTOFixture::create([
        'weeklyDigestTime' => '25:00',
        'weeklyDigestTimezone' => 'Not/AZone',
    ]);

    $violations = $validator->validate($dto);
    $paths = array_map(fn ($v) => $v->getPropertyPath(), iterator_to_array($violations));

    expect($paths)->toContain('weeklyDigestTime')
        ->and($paths)->toContain('weeklyDigestTimezone');
});
