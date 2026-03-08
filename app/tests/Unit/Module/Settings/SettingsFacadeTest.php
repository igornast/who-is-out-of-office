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
