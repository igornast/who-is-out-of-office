<?php

declare(strict_types=1);

use App\Infrastructure\DataNager\DateNagerFacade;
use App\Infrastructure\DataNager\Http\DateNagerClient;
use App\Shared\DTO\DataNager\NagerAvailableCountryDTO;
use App\Shared\DTO\DataNager\NagerPublicHolidayDTO;

beforeEach(function (): void {
    $this->client = mock(DateNagerClient::class);

    $this->facade = new DateNagerFacade(client: $this->client);
});

it('returns holidays as DTOs', function () {
    $this->client
        ->expects('fetchHolidays')
        ->once()
        ->with('DE', 2025)
        ->andReturn([
            ['date' => '2025-01-01', 'localName' => 'Neujahrstag', 'name' => "New Year's Day"],
            ['date' => '2025-12-25', 'localName' => 'Weihnachten', 'name' => 'Christmas Day'],
        ]);

    $result = $this->facade->getHolidaysForCountry('DE', 2025);

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(NagerPublicHolidayDTO::class)
        ->and($result[0]->date->format('Y-m-d'))->toBe('2025-01-01')
        ->and($result[0]->localName)->toBe('Neujahrstag')
        ->and($result[0]->name)->toBe("New Year's Day")
        ->and($result[1]->date->format('Y-m-d'))->toBe('2025-12-25')
        ->and($result[1]->localName)->toBe('Weihnachten')
        ->and($result[1]->name)->toBe('Christmas Day');
});

it('returns empty array when no holidays', function () {
    $this->client
        ->expects('fetchHolidays')
        ->once()
        ->with('XX', 2025)
        ->andReturn([]);

    $result = $this->facade->getHolidaysForCountry('XX', 2025);

    expect($result)->toBe([]);
});

it('returns available countries as DTOs', function () {
    $this->client
        ->expects('fetchAvailableCountries')
        ->once()
        ->andReturn([
            ['countryCode' => 'DE', 'name' => 'Germany'],
            ['countryCode' => 'PL', 'name' => 'Poland'],
        ]);

    $result = $this->facade->getAvailableCountries();

    expect($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(NagerAvailableCountryDTO::class)
        ->and($result[0]->countryCode)->toBe('DE')
        ->and($result[0]->name)->toBe('Germany')
        ->and($result[1]->countryCode)->toBe('PL')
        ->and($result[1]->name)->toBe('Poland');
});

it('returns empty array when no available countries', function () {
    $this->client
        ->expects('fetchAvailableCountries')
        ->once()
        ->andReturn([]);

    $result = $this->facade->getAvailableCountries();

    expect($result)->toBe([]);
});
