<?php

declare(strict_types=1);

use App\Module\LeaveRequest\UseCase\Query\CalculateWorkDaysQueryHandler;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Handler\LeaveRequest\Query\CalculateWorkdaysQuery;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;

beforeEach(function (): void {
    $this->holidayFacade = mock(HolidayFacadeInterface::class);

    $this->handler = new CalculateWorkDaysQueryHandler(holidayFacade: $this->holidayFacade);
});

it('calculates work days without public holidays', function () {
    $this->holidayFacade
        ->expects('getHolidayDaysForCountryBetweenDates')
        ->never();

    $query = new CalculateWorkdaysQuery(
        startDate: DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-16'),
        endDate: DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-25'),
        userWorkingDays: [1, 2, 3, 4, 5],
        holidayCalendarCountryCode: null,
    );

    expect($this->handler->handle($query))
    ->toBe(8);
});

it('return zero on incorrect dates ', function () {
    $this->holidayFacade
        ->expects('getHolidayDaysForCountryBetweenDates')
        ->never();

    $query = new CalculateWorkdaysQuery(
        startDate: DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-26'),
        endDate: DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-25'),
        userWorkingDays: [1, 2, 3, 4, 5],
        holidayCalendarCountryCode: null,
    );

    expect($this->handler->handle($query))
    ->toBe(0);
});

it('calculates work days with custom work schedule', function () {
    $this->holidayFacade
        ->expects('getHolidayDaysForCountryBetweenDates')
        ->never();

    $query = new CalculateWorkdaysQuery(
        startDate: DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-16'),
        endDate: DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-25'),
        userWorkingDays: [1, 3, 4],
        holidayCalendarCountryCode: null,
    );

    expect($this->handler->handle($query))
    ->toBe(5);
});

it('calculates work days with public holidays', function () {
    $startDate = DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-16');
    $endDate = DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-25');

    $this->holidayFacade
        ->expects('getHolidayDaysForCountryBetweenDates')
        ->once()
        ->with($startDate, $endDate, 'PL', null)
        ->andReturn([
            PublicHolidayDTOFixture::create(['date' => DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-16')]),
            PublicHolidayDTOFixture::create(['date' => DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-21')]),
        ]);

    $query = new CalculateWorkdaysQuery(
        startDate: $startDate,
        endDate: $endDate,
        userWorkingDays: [1, 2, 3, 4, 5],
        holidayCalendarCountryCode: 'PL',
    );

    expect($this->handler->handle($query))
    ->toBe(7);
});

it('calculates work days with public holidays and custom work schedule', function () {
    $startDate = DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-16');
    $endDate = DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-25');

    $this->holidayFacade
        ->expects('getHolidayDaysForCountryBetweenDates')
        ->once()
        ->with($startDate, $endDate, 'PL', null)
        ->andReturn([
            PublicHolidayDTOFixture::create(['date' => DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-16')]),
            PublicHolidayDTOFixture::create(['date' => DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-17')]),
        ]);

    $query = new CalculateWorkdaysQuery(
        startDate: $startDate,
        endDate: $endDate,
        userWorkingDays: [2, 3, 4, 5],
        holidayCalendarCountryCode: 'PL',
    );

    expect($this->handler->handle($query))
    ->toBe(5);
});

it('passes subdivision code when calculating work days with holidays', function () {
    $startDate = DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-16');
    $endDate = DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-25');

    $this->holidayFacade
        ->expects('getHolidayDaysForCountryBetweenDates')
        ->once()
        ->with($startDate, $endDate, 'DE', 'DE-BY')
        ->andReturn([
            PublicHolidayDTOFixture::create(['date' => DateTimeImmutable::createFromFormat('Y-m-d', '2025-06-16')]),
        ]);

    $query = new CalculateWorkdaysQuery(
        startDate: $startDate,
        endDate: $endDate,
        userWorkingDays: [1, 2, 3, 4, 5],
        holidayCalendarCountryCode: 'DE',
        subdivisionCode: 'DE-BY',
    );

    expect($this->handler->handle($query))
    ->toBe(7);
});
