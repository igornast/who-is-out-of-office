<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Module\Holiday\UseCase\Query\GetActiveCalendarsForCountryCodesQueryHandler;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayCalendarDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(PublicHolidayCalendarRepositoryInterface::class);

    $this->handler = new GetActiveCalendarsForCountryCodesQueryHandler(
        calendarRepository: $this->repository
    );
});

it('returns empty array without calling repository for empty input', function () {
    $this->repository->shouldNotReceive('findActiveByCountryCodes');

    $result = $this->handler->handle([]);

    expect($result)->toBe([]);
});

it('filters out null and empty country codes before calling repository', function () {
    $calendar = PublicHolidayCalendarDTOFixture::create(['countryCode' => 'US']);

    $this->repository
        ->expects('findActiveByCountryCodes')
        ->once()
        ->with(['US'])
        ->andReturn([$calendar]);

    $result = $this->handler->handle([null, '', 'US']);

    expect($result)->toBe([$calendar]);
});

it('passes through valid country codes to repository', function () {
    $calendar1 = PublicHolidayCalendarDTOFixture::create(['countryCode' => 'US']);
    $calendar2 = PublicHolidayCalendarDTOFixture::create(['countryCode' => 'DE']);

    $this->repository
        ->expects('findActiveByCountryCodes')
        ->once()
        ->with(['US', 'DE'])
        ->andReturn([$calendar1, $calendar2]);

    $result = $this->handler->handle(['US', 'DE']);

    expect($result)->toBe([$calendar1, $calendar2]);
});
