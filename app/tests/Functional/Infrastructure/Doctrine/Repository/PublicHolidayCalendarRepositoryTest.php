<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Repository\PublicHolidayCalendarRepository;
use App\Module\Holiday\Service\HolidayImportReconciler;
use App\Module\Holiday\UseCase\Command\SyncCalendarCommandHandler;
use App\Shared\DTO\DataNager\NagerPublicHolidayDTO;
use App\Shared\DTO\Holiday\HolidayReconciliationDTO;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\Facade\DateNagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

beforeEach(function (): void {
    static::bootKernel();
    $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    $this->repository = static::getContainer()->get(PublicHolidayCalendarRepository::class);
    $this->reconciler = static::getContainer()->get(HolidayImportReconciler::class);
    expect(static::getContainer()->get(SyncCalendarCommandHandler::class))->toBeInstanceOf(SyncCalendarCommandHandler::class);
    $this->today = new DateTimeImmutable('today');
});

afterEach(function (): void {
    $calendar = $this->repository->findByCountryCode('ZX');
    if (null !== $calendar) {
        $this->repository->delete($calendar->id->toString());
    }

    static::ensureKernelShutdown();
});

function zxHolidayCalendarDTO(array $holidays, int $year): PublicHolidayCalendarDTO
{
    return new PublicHolidayCalendarDTO(
        id: Uuid::uuid4(),
        countryCode: 'ZX',
        countryName: 'Testland',
        holidays: $holidays,
        lastSyncedYear: $year,
    );
}

it('keeps holiday ids when the same year is imported twice', function (): void {
    $year = (int) date('Y') + 1;
    $holidays = [
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'New Year', 'ZX', new DateTimeImmutable(sprintf('%d-01-01', $year))),
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Christmas Day', 'ZX', new DateTimeImmutable(sprintf('%d-12-25', $year))),
    ];

    $this->repository->applyHolidayReconciliation(
        zxHolidayCalendarDTO($holidays, $year),
        $year,
        new HolidayReconciliationDTO([], $holidays, []),
    );
    $this->entityManager->clear();
    $firstImport = $this->repository->findHolidaysForYear('ZX', $year);
    $firstIds = array_map(fn (PublicHolidayDTO $holiday) => $holiday->id, $firstImport);
    sort($firstIds);

    $importedAgain = [
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'New Year', 'ZX', new DateTimeImmutable(sprintf('%d-01-01', $year))),
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Christmas Day', 'ZX', new DateTimeImmutable(sprintf('%d-12-25', $year))),
    ];
    $reconciliation = $this->reconciler->reconcile($firstImport, $importedAgain, $this->today);
    $this->repository->applyHolidayReconciliation(zxHolidayCalendarDTO($importedAgain, $year), $year, $reconciliation);
    $this->entityManager->clear();
    $secondImport = $this->repository->findHolidaysForYear('ZX', $year);

    $secondIds = array_map(fn (PublicHolidayDTO $holiday) => $holiday->id, $secondImport);
    sort($secondIds);

    expect($secondImport)->toHaveCount(2)
        ->and($secondIds)->toBe($firstIds);
});

it('updates a renamed holiday in place and keeps its id', function (): void {
    $year = (int) date('Y') + 1;
    $original = new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Old Name', 'ZX', new DateTimeImmutable(sprintf('%d-05-01', $year)));
    $this->repository->applyHolidayReconciliation(
        zxHolidayCalendarDTO([$original], $year),
        $year,
        new HolidayReconciliationDTO([], [$original], []),
    );
    $this->entityManager->clear();
    $existing = $this->repository->findHolidaysForYear('ZX', $year);
    $existingId = $existing[0]->id;

    $renamed = new PublicHolidayDTO(Uuid::uuid4()->toString(), 'New Name', 'ZX', new DateTimeImmutable(sprintf('%d-05-01', $year)));
    $reconciliation = $this->reconciler->reconcile($existing, [$renamed], $this->today);
    $this->repository->applyHolidayReconciliation(zxHolidayCalendarDTO([$renamed], $year), $year, $reconciliation);
    $this->entityManager->clear();
    $result = $this->repository->findHolidaysForYear('ZX', $year);

    expect($result)->toHaveCount(1)
        ->and($result[0]->id)->toBe($existingId)
        ->and($result[0]->description)->toBe('New Name');
});

it('adds new holidays and deletes missing future ones', function (): void {
    $year = (int) date('Y') + 1;
    $initial = [
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'New Year', 'ZX', new DateTimeImmutable(sprintf('%d-01-01', $year))),
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Old Holiday', 'ZX', new DateTimeImmutable(sprintf('%d-12-25', $year))),
    ];
    $this->repository->applyHolidayReconciliation(
        zxHolidayCalendarDTO($initial, $year),
        $year,
        new HolidayReconciliationDTO([], $initial, []),
    );
    $this->entityManager->clear();
    $existing = $this->repository->findHolidaysForYear('ZX', $year);

    $imported = [
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'New Year', 'ZX', new DateTimeImmutable(sprintf('%d-01-01', $year))),
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'New Holiday', 'ZX', new DateTimeImmutable(sprintf('%d-07-04', $year))),
    ];
    $reconciliation = $this->reconciler->reconcile($existing, $imported, $this->today);
    $this->repository->applyHolidayReconciliation(zxHolidayCalendarDTO($imported, $year), $year, $reconciliation);
    $this->entityManager->clear();
    $result = $this->repository->findHolidaysForYear('ZX', $year);
    $descriptions = array_map(fn (PublicHolidayDTO $holiday) => $holiday->description, $result);

    expect($result)->toHaveCount(2)
        ->and($descriptions)->toContain('New Year')
        ->and($descriptions)->toContain('New Holiday')
        ->and($descriptions)->not->toContain('Old Holiday');
});

it('keeps missing holidays dated before today', function (): void {
    $year = (int) date('Y') - 1;
    $holiday = new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Past Holiday', 'ZX', new DateTimeImmutable(sprintf('%d-01-01', $year)));
    $this->repository->applyHolidayReconciliation(
        zxHolidayCalendarDTO([$holiday], $year),
        $year,
        new HolidayReconciliationDTO([], [$holiday], []),
    );
    $this->entityManager->clear();
    $existing = $this->repository->findHolidaysForYear('ZX', $year);
    $reconciliation = $this->reconciler->reconcile($existing, [], $this->today);
    $this->repository->applyHolidayReconciliation(zxHolidayCalendarDTO([], $year), $year, $reconciliation);
    $this->entityManager->clear();
    $result = $this->repository->findHolidaysForYear('ZX', $year);

    expect($result)->toHaveCount(1)
        ->and($result[0]->description)->toBe('Past Holiday');
});

it('leaves holidays of other years untouched when one year is re-imported', function (): void {
    $year = (int) date('Y') + 1;
    $holidays = [
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Old Holiday', 'ZX', new DateTimeImmutable(sprintf('%d-12-25', $year))),
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Next Year Holiday', 'ZX', new DateTimeImmutable(sprintf('%d-01-01', $year + 1))),
    ];
    $this->repository->applyHolidayReconciliation(
        zxHolidayCalendarDTO($holidays, $year),
        $year,
        new HolidayReconciliationDTO([], $holidays, []),
    );
    $this->entityManager->clear();
    $nextYearBefore = $this->repository->findHolidaysForYear('ZX', $year + 1);
    $existing = $this->repository->findHolidaysForYear('ZX', $year);

    $reconciliation = $this->reconciler->reconcile($existing, [], $this->today);
    $this->repository->applyHolidayReconciliation(zxHolidayCalendarDTO([], $year), $year, $reconciliation);
    $this->entityManager->clear();
    $nextYearAfter = $this->repository->findHolidaysForYear('ZX', $year + 1);

    expect($this->repository->findHolidaysForYear('ZX', $year))->toBe([])
        ->and($nextYearBefore)->toHaveCount(1)
        ->and($nextYearAfter)->toHaveCount(1)
        ->and($nextYearAfter[0]->id)->toBe($nextYearBefore[0]->id)
        ->and($nextYearAfter[0]->description)->toBe('Next Year Holiday');
});

it('creates the calendar on the first import and records the synced year on every import', function (): void {
    $year = (int) date('Y') + 1;
    expect($this->repository->findHolidaysForYear('ZX', $year))->toBe([]);

    $holidays = [
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'New Year', 'ZX', new DateTimeImmutable(sprintf('%d-01-01', $year))),
    ];
    $this->repository->applyHolidayReconciliation(
        zxHolidayCalendarDTO($holidays, $year),
        $year,
        new HolidayReconciliationDTO([], $holidays, []),
    );
    $this->entityManager->clear();
    $afterFirstImport = $this->repository->findByCountryCode('ZX');
    $imported = $this->repository->findHolidaysForYear('ZX', $year);

    $nextYear = $year + 1;
    $this->repository->applyHolidayReconciliation(
        zxHolidayCalendarDTO([], $nextYear),
        $nextYear,
        new HolidayReconciliationDTO([], [], []),
    );
    $this->entityManager->clear();
    $afterSecondImport = $this->repository->findByCountryCode('ZX');

    expect($afterFirstImport?->lastSyncedYear)->toBe($year)
        ->and($imported)->toHaveCount(1)
        ->and($imported[0]->description)->toBe('New Year')
        ->and($afterSecondImport?->lastSyncedYear)->toBe($nextYear);
});

it('updates isGlobal and counties in place and persists them', function (): void {
    $year = (int) date('Y') + 1;
    $original = new PublicHolidayDTO(
        Uuid::uuid4()->toString(),
        'Regional Day',
        'ZX',
        new DateTimeImmutable(sprintf('%d-10-03', $year)),
        isGlobal: true,
        counties: null,
    );
    $this->repository->applyHolidayReconciliation(
        zxHolidayCalendarDTO([$original], $year),
        $year,
        new HolidayReconciliationDTO([], [$original], []),
    );
    $this->entityManager->clear();
    $existing = $this->repository->findHolidaysForYear('ZX', $year);
    $existingId = $existing[0]->id;

    $changed = new PublicHolidayDTO(
        Uuid::uuid4()->toString(),
        'Regional Day',
        'ZX',
        new DateTimeImmutable(sprintf('%d-10-03', $year)),
        isGlobal: false,
        counties: ['ZX-A', 'ZX-B'],
    );
    $reconciliation = $this->reconciler->reconcile($existing, [$changed], $this->today);
    $this->repository->applyHolidayReconciliation(zxHolidayCalendarDTO([$changed], $year), $year, $reconciliation);
    $this->entityManager->clear();
    $result = $this->repository->findHolidaysForYear('ZX', $year);

    expect($result)->toHaveCount(1)
        ->and($result[0]->id)->toBe($existingId)
        ->and($result[0]->isGlobal)->toBeFalse()
        ->and($result[0]->counties)->toBe(['ZX-A', 'ZX-B']);
});

it('keeps ids for past regional holidays when counties change on the same date', function (): void {
    $year = (int) date('Y') - 1;
    $date = new DateTimeImmutable(sprintf('%d-04-06', $year));
    $initial = [
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Regional A', 'ZX', $date, isGlobal: false, counties: ['ZX-A']),
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Regional B', 'ZX', $date, isGlobal: false, counties: ['ZX-B']),
    ];
    $this->repository->applyHolidayReconciliation(
        zxHolidayCalendarDTO($initial, $year),
        $year,
        new HolidayReconciliationDTO([], $initial, []),
    );
    $this->entityManager->clear();
    $existing = $this->repository->findHolidaysForYear('ZX', $year);

    $imported = [
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Regional A', 'ZX', $date, isGlobal: false, counties: ['ZX-A', 'ZX-C']),
        new PublicHolidayDTO(Uuid::uuid4()->toString(), 'Regional B', 'ZX', $date, isGlobal: false, counties: ['ZX-B', 'ZX-D']),
    ];
    $reconciliation = $this->reconciler->reconcile($existing, $imported, $this->today);
    $this->repository->applyHolidayReconciliation(zxHolidayCalendarDTO($imported, $year), $year, $reconciliation);
    $this->entityManager->clear();
    $result = $this->repository->findHolidaysForYear('ZX', $year);
    $resultByDescription = [];
    $originalIdsByDescription = [];
    foreach ($existing as $holiday) {
        $originalIdsByDescription[$holiday->description] = $holiday->id;
    }
    foreach ($result as $holiday) {
        $resultByDescription[$holiday->description] = $holiday;
    }

    expect($result)->toHaveCount(2)
        ->and($resultByDescription['Regional A']->id)->toBe($originalIdsByDescription['Regional A'])
        ->and($resultByDescription['Regional B']->id)->toBe($originalIdsByDescription['Regional B'])
        ->and($resultByDescription['Regional A']->counties)->toBe(['ZX-A', 'ZX-C'])
        ->and($resultByDescription['Regional B']->counties)->toBe(['ZX-B', 'ZX-D']);
});

it('syncs through the handler and keeps holiday ids when the same year is synced again', function (): void {
    $year = (int) date('Y') + 1;
    $nagerHolidays = [
        new NagerPublicHolidayDTO(
            date: new DateTimeImmutable(sprintf('%d-01-01', $year)),
            localName: 'New Year',
            name: "New Year's Day",
            global: true,
            counties: null,
        ),
        new NagerPublicHolidayDTO(
            date: new DateTimeImmutable(sprintf('%d-12-25', $year)),
            localName: 'Christmas Day',
            name: 'Christmas Day',
            global: true,
            counties: null,
        ),
    ];
    $dateNager = mock(DateNagerInterface::class);
    $dateNager
        ->expects('getHolidaysForCountry')
        ->twice()
        ->with('ZX', $year)
        ->andReturn($nagerHolidays);
    $handler = new SyncCalendarCommandHandler(
        dateNager: $dateNager,
        calendarRepository: $this->repository,
        reconciler: $this->reconciler,
        clock: new MockClock(new DateTimeImmutable('today')),
    );

    $handler->handle('ZX', 'Testland', $year);
    $this->entityManager->clear();
    $firstIds = array_map(fn (PublicHolidayDTO $holiday) => $holiday->id, $this->repository->findHolidaysForYear('ZX', $year));
    sort($firstIds);

    $handler->handle('ZX', 'Testland', $year);
    $this->entityManager->clear();
    $secondImport = $this->repository->findHolidaysForYear('ZX', $year);
    $secondIds = array_map(fn (PublicHolidayDTO $holiday) => $holiday->id, $secondImport);
    sort($secondIds);

    expect($firstIds)->toHaveCount(2)
        ->and($secondImport)->toHaveCount(2)
        ->and($secondIds)->toBe($firstIds);
});
