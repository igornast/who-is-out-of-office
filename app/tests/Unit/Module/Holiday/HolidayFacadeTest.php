<?php

declare(strict_types=1);

use App\Module\Holiday\HolidayFacade;
use App\Module\Holiday\UseCase\Command\UpsertHolidayCalendarCommandHandler;
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

    $this->facade = new HolidayFacade(
        upsertHandler: $this->upsertHandler,
        holidayCalendarHandler: $this->holidayCalendarHandler,
        holidayDaysHandler: $this->holidayDaysHandler,
        holidayDaysGroupedByUserIdHandler: $this->holidayDaysGroupedByUserIdHandler
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
