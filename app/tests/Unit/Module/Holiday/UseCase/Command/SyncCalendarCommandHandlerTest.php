<?php

declare(strict_types=1);

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Module\Holiday\Service\HolidayImportReconciler;
use App\Module\Holiday\UseCase\Command\SyncCalendarCommandHandler;
use App\Shared\DTO\DataNager\NagerPublicHolidayDTO;
use App\Shared\DTO\Holiday\HolidayReconciliationDTO;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\Facade\DateNagerInterface;
use Symfony\Component\Clock\MockClock;

beforeEach(function (): void {
    $this->dateNager = mock(DateNagerInterface::class);
    $this->repository = mock(PublicHolidayCalendarRepositoryInterface::class);
    $this->clock = new MockClock(new DateTimeImmutable('2026-06-15 10:00'));

    $this->handler = new SyncCalendarCommandHandler(
        dateNager: $this->dateNager,
        calendarRepository: $this->repository,
        reconciler: new HolidayImportReconciler(),
        clock: $this->clock,
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
        ->expects('findHolidaysForYear')
        ->once()
        ->with($countryCode, $year)
        ->andReturn([]);

    $this->repository
        ->expects('applyHolidayReconciliation')
        ->once()
        ->withArgs(fn (PublicHolidayCalendarDTO $dto, int $syncYear, HolidayReconciliationDTO $reconciliation) => $dto->countryCode === $countryCode
                && $dto->countryName === $countryName
                && 1 === count($dto->holidays)
                && $syncYear === $year
                && [] === $reconciliation->toUpdate
                && 1 === count($reconciliation->toInsert)
                && [] === $reconciliation->toDelete);

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
        ->expects('findHolidaysForYear')
        ->once()
        ->with($countryCode, $year)
        ->andReturn([]);

    $this->repository
        ->expects('applyHolidayReconciliation')
        ->once()
        ->withArgs(function (PublicHolidayCalendarDTO $dto, int $syncYear, HolidayReconciliationDTO $reconciliation) use ($countryCode, $year) {
            expect($dto->countryCode)->toBe($countryCode)
                ->and($dto->holidays)->toHaveCount(2)
                ->and($dto->holidays[0]->isGlobal)->toBeTrue()
                ->and($dto->holidays[0]->counties)->toBeNull()
                ->and($dto->holidays[1]->isGlobal)->toBeFalse()
                ->and($dto->holidays[1]->counties)->toBe(['DE-BW', 'DE-BY', 'DE-ST'])
                ->and($syncYear)->toBe($year)
                ->and($reconciliation->toUpdate)->toBe([])
                ->and($reconciliation->toInsert)->toHaveCount(2)
                ->and($reconciliation->toDelete)->toBe([]);

            return true;
        });

    $this->handler->handle($countryCode, $countryName, $year);
});

it('reconciles existing holidays using the current date', function () {
    $countryCode = 'US';
    $countryName = 'United States';
    $year = 2026;
    $pastHoliday = new PublicHolidayDTO(
        id: 'E1',
        description: 'Past Holiday',
        countryCode: $countryCode,
        date: new DateTimeImmutable('2026-06-10'),
    );
    $futureHoliday = new PublicHolidayDTO(
        id: 'E2',
        description: 'Future Holiday',
        countryCode: $countryCode,
        date: new DateTimeImmutable('2026-12-25'),
    );
    $nagerHoliday = new NagerPublicHolidayDTO(
        date: new DateTimeImmutable('2026-07-04'),
        localName: 'Imported Holiday',
        name: 'Imported Holiday',
    );

    $this->dateNager
        ->expects('getHolidaysForCountry')
        ->once()
        ->with($countryCode, $year)
        ->andReturn([$nagerHoliday]);

    $this->repository
        ->expects('findHolidaysForYear')
        ->once()
        ->with($countryCode, $year)
        ->andReturn([$pastHoliday, $futureHoliday]);

    $this->repository
        ->expects('applyHolidayReconciliation')
        ->once()
        ->withArgs(function (PublicHolidayCalendarDTO $dto, int $syncYear, HolidayReconciliationDTO $reconciliation) use ($countryCode, $countryName, $year) {
            expect($dto->countryCode)->toBe($countryCode)
                ->and($dto->countryName)->toBe($countryName)
                ->and($syncYear)->toBe($year)
                ->and($reconciliation->toDelete)->toBe(['E2'])
                ->and($reconciliation->toInsert)->toHaveCount(1)
                ->and($reconciliation->toInsert[0]->description)->toBe('Imported Holiday')
                ->and($reconciliation->toInsert[0]->date)->toEqual(new DateTimeImmutable('2026-07-04'));

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
        ->expects('findHolidaysForYear')
        ->once()
        ->with($countryCode, $year)
        ->andReturn([]);

    $this->repository
        ->expects('applyHolidayReconciliation')
        ->once()
        ->withArgs(fn (PublicHolidayCalendarDTO $dto, int $syncYear, HolidayReconciliationDTO $reconciliation) => $dto->countryCode === $countryCode
                && $dto->countryName === $countryName
                && [] === $dto->holidays
                && $syncYear === $year
                && [] === $reconciliation->toUpdate
                && [] === $reconciliation->toInsert
                && [] === $reconciliation->toDelete);

    $this->handler->handle($countryCode, $countryName, $year);
});
