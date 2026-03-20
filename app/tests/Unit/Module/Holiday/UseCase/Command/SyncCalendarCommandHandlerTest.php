<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Module\Holiday\UseCase\Command\SyncCalendarCommandHandler;
use App\Shared\DTO\DataNager\NagerPublicHolidayDTO;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\Facade\DateNagerInterface;

beforeEach(function (): void {
    $this->dateNager = mock(DateNagerInterface::class);
    $this->repository = mock(PublicHolidayCalendarRepositoryInterface::class);

    $this->handler = new SyncCalendarCommandHandler(
        dateNager: $this->dateNager,
        calendarRepository: $this->repository
    );
});

it('fetches holidays and upserts calendar', function () {
    $countryCode = 'US';
    $countryName = 'United States';
    $year = 2026;

    $nagerHoliday = new NagerPublicHolidayDTO(
        date: new DateTimeImmutable('2026-01-01'),
        localName: 'New Year',
        name: "New Year's Day",
    );

    $this->dateNager
        ->expects('getHolidaysForCountry')
        ->once()
        ->with($countryCode, $year)
        ->andReturn([$nagerHoliday]);

    $this->repository
        ->expects('upsertByCountryCode')
        ->once()
        ->withArgs(fn (PublicHolidayCalendarDTO $dto, ?int $syncYear) => $dto->countryCode === $countryCode
                && $dto->countryName === $countryName
                && 1 === count($dto->holidays)
                && $syncYear === $year);

    $this->handler->handle($countryCode, $countryName, $year);
});

it('passes regional data through sync pipeline', function () {
    $countryCode = 'DE';
    $countryName = 'Germany';
    $year = 2026;

    $nationalHoliday = new NagerPublicHolidayDTO(
        date: new DateTimeImmutable('2026-01-01'),
        localName: 'Neujahr',
        name: 'New Year',
        global: true,
        counties: null,
    );

    $regionalHoliday = new NagerPublicHolidayDTO(
        date: new DateTimeImmutable('2026-01-06'),
        localName: 'Heilige Drei Könige',
        name: 'Epiphany',
        global: false,
        counties: ['DE-BW', 'DE-BY', 'DE-ST'],
    );

    $this->dateNager
        ->expects('getHolidaysForCountry')
        ->once()
        ->with($countryCode, $year)
        ->andReturn([$nationalHoliday, $regionalHoliday]);

    $this->repository
        ->expects('upsertByCountryCode')
        ->once()
        ->withArgs(function (PublicHolidayCalendarDTO $dto, ?int $syncYear) use ($countryCode, $year) {
            expect($dto->countryCode)->toBe($countryCode)
                ->and($dto->holidays)->toHaveCount(2)
                ->and($dto->holidays[0]->isGlobal)->toBeTrue()
                ->and($dto->holidays[0]->counties)->toBeNull()
                ->and($dto->holidays[1]->isGlobal)->toBeFalse()
                ->and($dto->holidays[1]->counties)->toBe(['DE-BW', 'DE-BY', 'DE-ST'])
                ->and($syncYear)->toBe($year);

            return true;
        });

    $this->handler->handle($countryCode, $countryName, $year);
});

it('syncs calendar with empty holidays', function () {
    $countryCode = 'NG';
    $countryName = 'Nigeria';
    $year = 2026;

    $this->dateNager
        ->expects('getHolidaysForCountry')
        ->once()
        ->with($countryCode, $year)
        ->andReturn([]);

    $this->repository
        ->expects('upsertByCountryCode')
        ->once()
        ->withArgs(fn (PublicHolidayCalendarDTO $dto, ?int $syncYear) => $dto->countryCode === $countryCode
                && $dto->countryName === $countryName
                && [] === $dto->holidays
                && $syncYear === $year);

    $this->handler->handle($countryCode, $countryName, $year);
});
