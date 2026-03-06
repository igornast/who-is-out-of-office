<?php

declare(strict_types=1);

use App\Module\Holiday\HolidayFacade;
use App\Module\Holiday\UseCase\Command\DeleteCalendarCommandHandler;
use App\Module\Holiday\UseCase\Command\SyncAllActiveCalendarsCommandHandler;
use App\Module\Holiday\UseCase\Command\SyncCalendarCommandHandler;
use App\Module\Holiday\UseCase\Command\ToggleCalendarActiveCommandHandler;
use App\Module\Holiday\UseCase\Command\UpsertHolidayCalendarCommandHandler;
use App\Module\Holiday\UseCase\Query\GetAllCalendarsQueryHandler;
use App\Module\Holiday\UseCase\Query\GetHolidayCalendarForCountryQueryHandler;
use App\Module\Holiday\UseCase\Query\GetHolidayDaysForCountryBetweenDatesQueryHandler;
use App\Module\Holiday\UseCase\Query\GetHolidayDaysGroupedByUserIdBetweenDatesQueryHandler;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayCalendarDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->upsertHandler = mock(UpsertHolidayCalendarCommandHandler::class);
    $this->holidayCalendarHandler = mock(GetHolidayCalendarForCountryQueryHandler::class);
    $this->holidayDaysHandler = mock(GetHolidayDaysForCountryBetweenDatesQueryHandler::class);
    $this->holidayDaysGroupedByUserIdHandler = mock(GetHolidayDaysGroupedByUserIdBetweenDatesQueryHandler::class);
    $this->getAllCalendarsHandler = mock(GetAllCalendarsQueryHandler::class);
    $this->toggleCalendarActiveHandler = mock(ToggleCalendarActiveCommandHandler::class);
    $this->syncCalendarHandler = mock(SyncCalendarCommandHandler::class);
    $this->syncAllActiveCalendarsHandler = mock(SyncAllActiveCalendarsCommandHandler::class);
    $this->deleteCalendarHandler = mock(DeleteCalendarCommandHandler::class);

    $this->facade = new HolidayFacade(
        upsertHandler: $this->upsertHandler,
        holidayCalendarHandler: $this->holidayCalendarHandler,
        holidayDaysHandler: $this->holidayDaysHandler,
        holidayDaysGroupedByUserIdHandler: $this->holidayDaysGroupedByUserIdHandler,
        getAllCalendarsHandler: $this->getAllCalendarsHandler,
        toggleCalendarActiveHandler: $this->toggleCalendarActiveHandler,
        syncCalendarHandler: $this->syncCalendarHandler,
        syncAllActiveCalendarsHandler: $this->syncAllActiveCalendarsHandler,
        deleteCalendarHandler: $this->deleteCalendarHandler,
    );
});

it('calls handler to get holiday calendar for country', function () {
    $countryCode = 'US';
    $expectedCalendar = PublicHolidayCalendarDTOFixture::create();

    $this->holidayCalendarHandler
        ->expects('handle')
        ->once()
        ->with($countryCode)
        ->andReturn($expectedCalendar);

    $this->facade->getHolidayCalendarForCountry($countryCode);
});

it('calls handler to upsert holiday calendar', function () {
    $calendarDTO = PublicHolidayCalendarDTOFixture::create([
        'id' => Uuid::uuid4(),
        'countryCode' => 'NG',
        'countryName' => 'Nigeria',
    ]);

    $this->upsertHandler
        ->expects('handle')
        ->once()
        ->with($calendarDTO);

    $this->facade->upsertHolidayCalendar($calendarDTO);
});

it('calls handler to get holiday days for country between dates', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-12-31');
    $countryCode = 'US';

    $this->holidayDaysHandler
        ->expects('handle')
        ->once()
        ->with($startDate, $endDate, $countryCode)
        ->andReturn([]);

    $this->facade->getHolidayDaysForCountryBetweenDates($startDate, $endDate, $countryCode);
});

it('calls handler to get holidays grouped by user id', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');

    $this->holidayDaysGroupedByUserIdHandler
        ->expects('handle')
        ->once()
        ->with($startDate, $endDate)
        ->andReturn([]);

    $this->facade->getHolidaysForDatesGroupedByUserId($startDate, $endDate);
});

it('calls handler to get all calendars', function () {
    $calendar1 = PublicHolidayCalendarDTOFixture::create(['countryCode' => 'US']);
    $calendar2 = PublicHolidayCalendarDTOFixture::create(['countryCode' => 'GB']);

    $this->getAllCalendarsHandler
        ->expects('handle')
        ->once()
        ->andReturn([$calendar1, $calendar2]);

    $result = $this->facade->getAllCalendars();

    expect($result)->toBe([$calendar1, $calendar2]);
});

it('calls handler to toggle calendar active', function () {
    $calendarId = Uuid::uuid4()->toString();

    $this->toggleCalendarActiveHandler
        ->expects('handle')
        ->once()
        ->with($calendarId, false);

    $this->facade->toggleCalendarActive($calendarId, false);
});

it('calls handler to sync calendar', function () {
    $this->syncCalendarHandler
        ->expects('handle')
        ->once()
        ->with('US', 'United States', 2026);

    $this->facade->syncCalendar('US', 'United States', 2026);
});

it('calls handler to sync all active calendars', function () {
    $this->syncAllActiveCalendarsHandler
        ->expects('handle')
        ->once()
        ->with(2026);

    $this->facade->syncAllActiveCalendars(2026);
});

it('calls handler to delete calendar', function () {
    $calendarId = Uuid::uuid4()->toString();

    $this->deleteCalendarHandler
        ->expects('handle')
        ->once()
        ->with($calendarId);

    $this->facade->deleteCalendar($calendarId);
});
