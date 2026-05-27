<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayRepositoryInterface;
use App\Module\Holiday\UseCase\Query\GetHolidaysForCalendarsBetweenQueryHandler;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(PublicHolidayRepositoryInterface::class);

    $this->handler = new GetHolidaysForCalendarsBetweenQueryHandler(
        publicHolidayRepository: $this->repository
    );
});

it('returns holidays found by repository for the given calendars and date range', function () {
    $startDate = new DateTimeImmutable('2026-01-01');
    $endDate = new DateTimeImmutable('2026-12-31');
    $calendarIds = ['calendar-1', 'calendar-2'];

    $expectedHolidays = [
        PublicHolidayDTOFixture::create(),
        PublicHolidayDTOFixture::create(),
    ];

    $this->repository
        ->expects('findBetweenDatesForCalendarIds')
        ->once()
        ->with($calendarIds, $startDate, $endDate)
        ->andReturn($expectedHolidays);

    $result = $this->handler->handle($calendarIds, $startDate, $endDate);

    expect($result)->toBe($expectedHolidays)
        ->and($result)->toHaveCount(2);
});

it('returns empty array when repository finds no holidays', function () {
    $startDate = new DateTimeImmutable('2026-01-01');
    $endDate = new DateTimeImmutable('2026-12-31');

    $this->repository
        ->expects('findBetweenDatesForCalendarIds')
        ->once()
        ->with([], $startDate, $endDate)
        ->andReturn([]);

    $result = $this->handler->handle([], $startDate, $endDate);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});
