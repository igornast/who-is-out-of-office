<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Module\Holiday\UseCase\Query\GetAllCalendarsQueryHandler;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayCalendarDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(PublicHolidayCalendarRepositoryInterface::class);

    $this->handler = new GetAllCalendarsQueryHandler(
        calendarRepository: $this->repository
    );
});

it('returns all calendars from repository', function () {
    $calendar1 = PublicHolidayCalendarDTOFixture::create(['countryCode' => 'US', 'countryName' => 'United States']);
    $calendar2 = PublicHolidayCalendarDTOFixture::create(['countryCode' => 'GB', 'countryName' => 'United Kingdom']);

    $this->repository
        ->expects('findAll')
        ->once()
        ->andReturn([$calendar1, $calendar2]);

    $result = $this->handler->handle();

    expect($result)->toBe([$calendar1, $calendar2]);
});

it('returns empty array when no calendars exist', function () {
    $this->repository
        ->expects('findAll')
        ->once()
        ->andReturn([]);

    $result = $this->handler->handle();

    expect($result)->toBe([]);
});
