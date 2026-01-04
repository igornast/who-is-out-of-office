<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Module\Holiday\UseCase\Query\GetHolidayCalendarForCountryQueryHandler;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayCalendarDTOFixture;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->calendarRepo = mock(PublicHolidayCalendarRepositoryInterface::class);

    $this->handler = new GetHolidayCalendarForCountryQueryHandler(calendarRepo: $this->calendarRepo);
});

it('returns holiday calendar for country code', function () {
    $countryCode = 'US';

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

    $expectedCalendar = PublicHolidayCalendarDTOFixture::create([
        'id' => Uuid::uuid4(),
        'countryCode' => 'US',
        'countryName' => 'United States',
        'holidays' => [$holiday1, $holiday2],
    ]);

    $this->calendarRepo
        ->expects('findByCountryCode')
        ->once()
        ->with($countryCode)
        ->andReturn($expectedCalendar);

    $result = $this->handler->handle($countryCode);

    expect($result)->toBe($expectedCalendar)
        ->and($result)->toBeInstanceOf(PublicHolidayCalendarDTO::class)
        ->and($result->countryCode)->toBe('US')
        ->and($result->countryName)->toBe('United States')
        ->and($result->holidays)->toHaveCount(2);
});

it('returns holiday calendar with empty holidays list', function () {
    $countryCode = 'NG';

    $expectedCalendar = PublicHolidayCalendarDTOFixture::create([
        'id' => Uuid::uuid4(),
        'countryCode' => 'NG',
        'countryName' => 'Nigeria',
        'holidays' => [],
    ]);

    $this->calendarRepo
        ->expects('findByCountryCode')
        ->once()
        ->with($countryCode)
        ->andReturn($expectedCalendar);

    $result = $this->handler->handle($countryCode);

    expect($result)->toBe($expectedCalendar)
        ->and($result->countryCode)->toBe('NG')
        ->and($result->holidays)->toBeEmpty();
});

it('throws exception when holiday calendar not found', function () {
    $countryCode = 'XX';

    $this->calendarRepo
        ->expects('findByCountryCode')
        ->once()
        ->with($countryCode)
        ->andReturn(null);

    expect(fn () => $this->handler->handle($countryCode))
        ->toThrow(RuntimeException::class, "Holiday calendar for country 'XX' not found.");
});
