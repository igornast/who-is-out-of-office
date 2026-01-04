<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Module\Holiday\UseCase\Command\UpsertHolidayCalendarCommandHandler;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayCalendarDTOFixture;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->calendarRepository = mock(PublicHolidayCalendarRepositoryInterface::class);

    $this->handler = new UpsertHolidayCalendarCommandHandler(
        calendarRepository: $this->calendarRepository
    );
});

it('upserts holiday calendar', function () {
    $holiday1 = PublicHolidayDTOFixture::create([
        'id' => 'holiday-1',
        'description' => 'New Year',
        'countryCode' => 'US',
        'date' => new DateTimeImmutable('2025-01-01'),
    ]);

    $holiday2 = PublicHolidayDTOFixture::create([
        'id' => 'holiday-2',
        'description' => 'Independence Day',
        'countryCode' => 'US',
        'date' => new DateTimeImmutable('2025-07-04'),
    ]);

    $calendarDTO = PublicHolidayCalendarDTOFixture::create([
        'id' => Uuid::uuid4(),
        'countryCode' => 'US',
        'countryName' => 'United States',
        'holidays' => [$holiday1, $holiday2],
    ]);

    $this->calendarRepository
        ->expects('upsertByCountryCode')
        ->once()
        ->with($calendarDTO);

    $this->handler->handle($calendarDTO);
});

it('upserts holiday calendar with empty holidays', function () {
    $calendarDTO = PublicHolidayCalendarDTOFixture::create([
        'id' => Uuid::uuid4(),
        'countryCode' => 'NG',
        'countryName' => 'Nigeria',
        'holidays' => [],
    ]);

    $this->calendarRepository
        ->expects('upsertByCountryCode')
        ->once()
        ->with($calendarDTO);

    $this->handler->handle($calendarDTO);
});

it('upserts holiday calendar for different countries', function () {
    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-uk-1',
        'description' => 'Boxing Day',
        'countryCode' => 'GB',
        'date' => new DateTimeImmutable('2025-12-26'),
    ]);

    $calendarDTO = PublicHolidayCalendarDTOFixture::create([
        'id' => Uuid::uuid4(),
        'countryCode' => 'GB',
        'countryName' => 'United Kingdom',
        'holidays' => [$holiday],
    ]);

    $this->calendarRepository
        ->expects('upsertByCountryCode')
        ->once()
        ->with($calendarDTO);

    $this->handler->handle($calendarDTO);
});
