<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayRepositoryInterface;
use App\Module\Holiday\UseCase\Query\GetHolidayDaysForCountryBetweenDatesQueryHandler;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;

beforeEach(function (): void {
    $this->publicHolidayRepository = mock(PublicHolidayRepositoryInterface::class);

    $this->handler = new GetHolidayDaysForCountryBetweenDatesQueryHandler(
        publicHolidayRepository: $this->publicHolidayRepository
    );
});

it('returns holidays for country between dates', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-12-31');
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

    $holiday3 = PublicHolidayDTOFixture::create([
        'id' => 'holiday-3',
        'description' => 'Christmas',
        'countryCode' => 'US',
        'date' => new DateTimeImmutable('2025-12-25'),
    ]);

    $expectedHolidays = [$holiday1, $holiday2, $holiday3];

    $this->publicHolidayRepository
        ->expects('findBetweenDatesForCountryCode')
        ->once()
        ->with($startDate, $endDate, $countryCode, null)
        ->andReturn($expectedHolidays);

    $result = $this->handler->handle($startDate, $endDate, $countryCode);

    expect($result)->toBe($expectedHolidays)
        ->and($result)->toHaveCount(3)
        ->and($result[0])->toBeInstanceOf(PublicHolidayDTO::class)
        ->and($result[1])->toBeInstanceOf(PublicHolidayDTO::class)
        ->and($result[2])->toBeInstanceOf(PublicHolidayDTO::class);
});

it('returns empty array when no holidays found for country between dates', function () {
    $startDate = new DateTimeImmutable('2025-06-01');
    $endDate = new DateTimeImmutable('2025-06-30');
    $countryCode = 'NG';

    $this->publicHolidayRepository
        ->expects('findBetweenDatesForCountryCode')
        ->once()
        ->with($startDate, $endDate, $countryCode, null)
        ->andReturn([]);

    $result = $this->handler->handle($startDate, $endDate, $countryCode);

    expect($result)->toBe([])
        ->and($result)->toBeEmpty();
});

it('returns holidays for specific month range', function () {
    $startDate = new DateTimeImmutable('2025-12-01');
    $endDate = new DateTimeImmutable('2025-12-31');
    $countryCode = 'US';

    $christmasHoliday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-christmas',
        'description' => 'Christmas',
        'countryCode' => 'US',
        'date' => new DateTimeImmutable('2025-12-25'),
    ]);

    $expectedHolidays = [$christmasHoliday];

    $this->publicHolidayRepository
        ->expects('findBetweenDatesForCountryCode')
        ->once()
        ->with($startDate, $endDate, $countryCode, null)
        ->andReturn($expectedHolidays);

    $result = $this->handler->handle($startDate, $endDate, $countryCode);

    expect($result)->toBe($expectedHolidays)
        ->and($result)->toHaveCount(1)
        ->and($result[0]->description)->toBe('Christmas');
});

it('handles different country codes', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-01-31');
    $countryCode = 'NG';

    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-ng-1',
        'description' => 'New Year Day',
        'countryCode' => 'NG',
        'date' => new DateTimeImmutable('2025-01-01'),
    ]);

    $expectedHolidays = [$holiday];

    $this->publicHolidayRepository
        ->expects('findBetweenDatesForCountryCode')
        ->once()
        ->with($startDate, $endDate, 'NG', null)
        ->andReturn($expectedHolidays);

    $result = $this->handler->handle($startDate, $endDate, $countryCode);

    expect($result)->toBe($expectedHolidays)
        ->and($result[0]->countryCode)->toBe('NG');
});

it('passes subdivision code to repository', function () {
    $startDate = new DateTimeImmutable('2025-01-01');
    $endDate = new DateTimeImmutable('2025-12-31');
    $countryCode = 'DE';
    $subdivisionCode = 'DE-BY';

    $holiday = PublicHolidayDTOFixture::create([
        'id' => 'holiday-de-1',
        'description' => 'Epiphany',
        'countryCode' => 'DE',
        'date' => new DateTimeImmutable('2025-01-06'),
        'isGlobal' => false,
        'counties' => ['DE-BW', 'DE-BY', 'DE-ST'],
    ]);

    $this->publicHolidayRepository
        ->expects('findBetweenDatesForCountryCode')
        ->once()
        ->with($startDate, $endDate, $countryCode, $subdivisionCode)
        ->andReturn([$holiday]);

    $result = $this->handler->handle($startDate, $endDate, $countryCode, $subdivisionCode);

    expect($result)->toHaveCount(1)
        ->and($result[0]->isGlobal)->toBeFalse()
        ->and($result[0]->counties)->toBe(['DE-BW', 'DE-BY', 'DE-ST']);
});
