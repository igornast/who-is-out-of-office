<?php

declare(strict_types=1);

use App\Module\Settings\SettingsFacade;
use App\Module\Settings\Exception\InvalidAppSettingTypeException;
use App\Module\Settings\UseCase\Command\UpdateAppSettingsValueCommandHandler;
use App\Module\Settings\UseCase\Query\GetAllAppSettingsQueryHandler;
use App\Module\Settings\UseCase\Query\GetAppSettingsValueQueryHandler;
use App\Shared\Enum\AppSettingsEnum;
use App\Tests\_fixtures\Shared\DTO\Settings\AppSettingsDTOFixture;

beforeEach(function (): void {
    $this->appSettingValueHandler = mock(GetAppSettingsValueQueryHandler::class);
    $this->getAllAppSettingsQueryHandler = mock(GetAllAppSettingsQueryHandler::class);
    $this->updateAppSettingsValueCommandHandler = mock(UpdateAppSettingsValueCommandHandler::class);

    $this->facade = new SettingsFacade(
        appSettingValueHandler: $this->appSettingValueHandler,
        getAllAppSettingsQueryHandler: $this->getAllAppSettingsQueryHandler,
        updateAppSettingsValueCommandHandler: $this->updateAppSettingsValueCommandHandler,
    );
});

it('returns true when auto approve is enabled', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::AUTO_APPROVE)
        ->andReturn(true);

    expect($this->facade->isAutoApprove())->toBeTrue();
});

it('returns false when auto approve is disabled', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::AUTO_APPROVE)
        ->andReturn(false);

    expect($this->facade->isAutoApprove())->toBeFalse();
});

it('throws exception when auto approve is not bool', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::AUTO_APPROVE)
        ->andReturn('yes');

    $this->facade->isAutoApprove();
})->throws(InvalidAppSettingTypeException::class);

it('returns auto approve delay', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::AUTO_APPROVE_DELAY)
        ->andReturn(5);

    expect($this->facade->autoApproveDelay())->toBe(5);
});

it('throws exception when auto approve delay is not int', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::AUTO_APPROVE_DELAY)
        ->andReturn('five');

    $this->facade->autoApproveDelay();
})->throws(InvalidAppSettingTypeException::class);

it('returns default annual allowance', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::DEFAULT_ANNUAL_ALLOWANCE)
        ->andReturn(24);

    expect($this->facade->defaultAnnualAllowance())->toBe(24);
});

it('throws exception when default annual allowance is not int', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::DEFAULT_ANNUAL_ALLOWANCE)
        ->andReturn(null);

    $this->facade->defaultAnnualAllowance();
})->throws(InvalidAppSettingTypeException::class);

it('returns min notice days', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::MIN_NOTICE_DAYS)
        ->andReturn(3);

    expect($this->facade->minNoticeDays())->toBe(3);
});

it('throws exception when min notice days is not int', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::MIN_NOTICE_DAYS)
        ->andReturn(true);

    $this->facade->minNoticeDays();
})->throws(InvalidAppSettingTypeException::class);

it('returns max consecutive days', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::MAX_CONSECUTIVE_DAYS)
        ->andReturn(10);

    expect($this->facade->maxConsecutiveDays())->toBe(10);
});

it('throws exception when max consecutive days is not int', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::MAX_CONSECUTIVE_DAYS)
        ->andReturn(false);

    $this->facade->maxConsecutiveDays();
})->throws(InvalidAppSettingTypeException::class);

it('returns true when skip weekend holidays is enabled', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::SKIP_WEEKEND_HOLIDAYS)
        ->andReturn(true);

    expect($this->facade->skipWeekendHolidays())->toBeTrue();
});

it('returns false when skip weekend holidays value is null', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::SKIP_WEEKEND_HOLIDAYS)
        ->andReturn(null);

    expect($this->facade->skipWeekendHolidays())->toBeFalse();
});

it('throws exception when skip weekend holidays is not bool', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::SKIP_WEEKEND_HOLIDAYS)
        ->andReturn('yes');

    $this->facade->skipWeekendHolidays();
})->throws(InvalidAppSettingTypeException::class);

it('returns true when slack status sync is enabled', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::SLACK_STATUS_SYNC_ENABLED)
        ->andReturn(true);

    expect($this->facade->isSlackStatusSyncEnabled())->toBeTrue();
});

it('returns false when slack status sync value is null (backward compatible)', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::SLACK_STATUS_SYNC_ENABLED)
        ->andReturn(null);

    expect($this->facade->isSlackStatusSyncEnabled())->toBeFalse();
});

it('returns false when slack status sync is disabled', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::SLACK_STATUS_SYNC_ENABLED)
        ->andReturn(false);

    expect($this->facade->isSlackStatusSyncEnabled())->toBeFalse();
});

it('throws exception when slack status sync is not bool', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::SLACK_STATUS_SYNC_ENABLED)
        ->andReturn('yes');

    $this->facade->isSlackStatusSyncEnabled();
})->throws(InvalidAppSettingTypeException::class);

it('delegates getAllSettings to handler', function () {
    $settingsDTO = AppSettingsDTOFixture::create();

    $this->getAllAppSettingsQueryHandler
        ->expects('handle')
        ->once()
        ->andReturn($settingsDTO);

    expect($this->facade->getAllSettings())->toBe($settingsDTO);
});

it('delegates updateAllSettings to handler', function () {
    $settingsDTO = AppSettingsDTOFixture::create(['autoApprove' => false]);

    $this->updateAppSettingsValueCommandHandler
        ->expects('handle')
        ->once()
        ->with($settingsDTO);

    $this->facade->updateAllSettings($settingsDTO);
});

it('returns organization name', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::ORGANIZATION_NAME)
        ->andReturn('Acme Inc.');

    expect($this->facade->organizationName())->toBe('Acme Inc.');
});

it('throws exception when organization name is not string', function () {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::ORGANIZATION_NAME)
        ->andReturn(42);

    $this->facade->organizationName();
})->throws(InvalidAppSettingTypeException::class);

it('composes the weekly digest cron expression from day and time', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_DAY)
        ->andReturn('MON');
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_TIME)
        ->andReturn('08:15');

    expect($this->facade->weeklyDigestCronExpression())->toBe('15 8 * * MON');
});

it('returns the weekly digest timezone', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_TIMEZONE)
        ->andReturn('Europe/Berlin');

    expect($this->facade->weeklyDigestTimezone())->toBe('Europe/Berlin');
});

it('throws when weekly digest timezone is not a string', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_TIMEZONE)
        ->andReturn(42);

    $this->facade->weeklyDigestTimezone();
})->throws(InvalidAppSettingTypeException::class);

it('falls back to default day when weekly digest day is missing (backward compatible)', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_DAY)
        ->andReturn(null);
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_TIME)
        ->andReturn('08:15');

    expect($this->facade->weeklyDigestCronExpression())->toBe('15 8 * * MON');
});

it('falls back to default time when weekly digest time is missing (backward compatible)', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_DAY)
        ->andReturn('MON');
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_TIME)
        ->andReturn(null);

    expect($this->facade->weeklyDigestCronExpression())->toBe('15 8 * * MON');
});

it('falls back to default day when weekly digest day is an unknown value', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_DAY)
        ->andReturn('FUNDAY');
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_TIME)
        ->andReturn('08:15');

    expect($this->facade->weeklyDigestCronExpression())->toBe('15 8 * * MON');
});

it('falls back to default time when weekly digest time is malformed', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_DAY)
        ->andReturn('MON');
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_TIME)
        ->andReturn('99:99');

    expect($this->facade->weeklyDigestCronExpression())->toBe('15 8 * * MON');
});

it('throws when weekly digest day is not a string', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_DAY)
        ->andReturn(42);

    $this->facade->weeklyDigestCronExpression();
})->throws(InvalidAppSettingTypeException::class);

it('throws when weekly digest time is not a string', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_DAY)
        ->andReturn('MON');
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_TIME)
        ->andReturn(42);

    $this->facade->weeklyDigestCronExpression();
})->throws(InvalidAppSettingTypeException::class);

it('falls back to UTC when weekly digest timezone is missing (backward compatible)', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_TIMEZONE)
        ->andReturn(null);

    expect($this->facade->weeklyDigestTimezone())->toBe('UTC');
});

it('falls back to UTC when weekly digest timezone is not a known identifier', function (): void {
    $this->appSettingValueHandler
        ->expects('handle')
        ->with(AppSettingsEnum::WEEKLY_DIGEST_TIMEZONE)
        ->andReturn('Not/AZone');

    expect($this->facade->weeklyDigestTimezone())->toBe('UTC');
});
