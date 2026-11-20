<?php

declare(strict_types=1);

use App\Module\Holiday\Service\HolidayImportReconciler;
use App\Tests\_fixtures\Shared\DTO\Holiday\PublicHolidayDTOFixture;

beforeEach(function (): void {
    $this->reconciler = new HolidayImportReconciler();
    $this->today = new DateTimeImmutable('2026-06-15');
});

it('matches identical holidays and changes nothing', function (): void {
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'description' => 'New Year',
            'countryCode' => 'DE',
            'date' => new DateTimeImmutable('2026-01-01'),
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E2',
            'description' => 'Labour Day',
            'countryCode' => 'DE',
            'date' => new DateTimeImmutable('2026-05-01'),
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I1',
            'description' => 'New Year',
            'countryCode' => 'DE',
            'date' => new DateTimeImmutable('2026-01-01'),
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I2',
            'description' => 'Labour Day',
            'countryCode' => 'DE',
            'date' => new DateTimeImmutable('2026-05-01'),
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('treats null and empty counties and county order as equal', function (): void {
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'description' => 'Holiday A',
            'countryCode' => 'DE',
            'date' => new DateTimeImmutable('2026-06-15'),
            'counties' => null,
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E2',
            'description' => 'Holiday B',
            'countryCode' => 'DE',
            'date' => new DateTimeImmutable('2026-06-16'),
            'counties' => ['DE-BY', 'DE-BW'],
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I1',
            'description' => 'Holiday A',
            'countryCode' => 'DE',
            'date' => new DateTimeImmutable('2026-06-15'),
            'counties' => [],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I2',
            'description' => 'Holiday B',
            'countryCode' => 'DE',
            'date' => new DateTimeImmutable('2026-06-16'),
            'counties' => ['DE-BW', 'DE-BY'],
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('keeps duplicate descriptions on one date apart by counties', function (): void {
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'description' => 'Ostermontag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-ZH'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E2',
            'description' => 'Ostermontag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-BE'],
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I1',
            'description' => 'Ostermontag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-ZH'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I2',
            'description' => 'Ostermontag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-BE'],
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('updates in place when exactly one holiday per date is unmatched on each side', function (): void {
    $existingHoliday = PublicHolidayDTOFixture::create([
        'id' => 'E1',
        'description' => 'Labour Day',
        'date' => new DateTimeImmutable('2026-05-01'),
    ]);
    $importedHoliday = PublicHolidayDTOFixture::create([
        'id' => 'I1',
        'description' => 'Workers Day',
        'date' => new DateTimeImmutable('2026-05-01'),
    ]);

    $result = $this->reconciler->reconcile([$existingHoliday], [$importedHoliday], $this->today);

    expect($result->toUpdate)->toBe(['E1' => $importedHoliday])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('does not pair holidays when a date has several unmatched on one side', function (): void {
    $existingHoliday = PublicHolidayDTOFixture::create([
        'id' => 'E1',
        'description' => 'A',
        'date' => new DateTimeImmutable('2026-09-25'),
    ]);
    $importedHolidays = [
        PublicHolidayDTOFixture::create([
            'id' => 'I1',
            'description' => 'B',
            'date' => new DateTimeImmutable('2026-09-25'),
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I2',
            'description' => 'C',
            'date' => new DateTimeImmutable('2026-09-25'),
        ]),
    ];

    $result = $this->reconciler->reconcile([$existingHoliday], $importedHolidays, $this->today);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe($importedHolidays)
        ->and($result->toDelete)->toBe(['E1']);
});

it('updates in place when only isGlobal changes on the same date and description', function (): void {
    $existingHoliday = PublicHolidayDTOFixture::create([
        'id' => 'E1',
        'description' => 'Regional Day',
        'date' => new DateTimeImmutable('2026-10-03'),
        'isGlobal' => true,
        'counties' => null,
    ]);
    $importedHoliday = PublicHolidayDTOFixture::create([
        'id' => 'I1',
        'description' => 'Regional Day',
        'date' => new DateTimeImmutable('2026-10-03'),
        'isGlobal' => false,
        'counties' => null,
    ]);

    $result = $this->reconciler->reconcile([$existingHoliday], [$importedHoliday], $this->today);

    expect($result->toUpdate)->toBe(['E1' => $importedHoliday])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('updates in place when only counties change on the same date and description', function (): void {
    $existingHoliday = PublicHolidayDTOFixture::create([
        'id' => 'E1',
        'description' => 'Regional Day',
        'date' => new DateTimeImmutable('2026-10-03'),
        'isGlobal' => false,
        'counties' => ['DE-BY'],
    ]);
    $importedHoliday = PublicHolidayDTOFixture::create([
        'id' => 'I1',
        'description' => 'Regional Day',
        'date' => new DateTimeImmutable('2026-10-03'),
        'isGlobal' => false,
        'counties' => ['DE-BW'],
    ]);

    $result = $this->reconciler->reconcile([$existingHoliday], [$importedHoliday], $this->today);

    expect($result->toUpdate)->toBe(['E1' => $importedHoliday])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('updates several holidays on one date by name when their regions change', function (): void {
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'description' => 'Ostermontag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-ZH'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E2',
            'description' => 'Karfreitag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-BE'],
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I1',
            'description' => 'Ostermontag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-ZH', 'CH-ZG'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I2',
            'description' => 'Karfreitag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-BE', 'CH-FR'],
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe(['E1' => $imported[0], 'E2' => $imported[1]])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('pairs duplicate names on one date without inserting or deleting', function (): void {
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'description' => 'Ostermontag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-ZH'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E2',
            'description' => 'Ostermontag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-BE'],
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I1',
            'description' => 'Ostermontag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-ZH', 'CH-ZG'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I2',
            'description' => 'Ostermontag',
            'date' => new DateTimeImmutable('2026-04-06'),
            'counties' => ['CH-BE', 'CH-FR'],
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe(['E1' => $imported[0], 'E2' => $imported[1]])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('matches by name before pairing renames on the same date', function (): void {
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'description' => 'A',
            'date' => new DateTimeImmutable('2026-09-25'),
            'counties' => ['X'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E2',
            'description' => 'B',
            'date' => new DateTimeImmutable('2026-09-25'),
            'counties' => ['Y'],
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I1',
            'description' => 'A',
            'date' => new DateTimeImmutable('2026-09-25'),
            'counties' => ['X', 'Z'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I2',
            'description' => 'C',
            'date' => new DateTimeImmutable('2026-09-25'),
            'counties' => ['Y'],
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe(['E1' => $imported[0], 'E2' => $imported[1]])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('keeps existing and imported indexes apart when pairing', function (): void {
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E_A',
            'description' => 'A',
            'date' => new DateTimeImmutable('2026-06-01'),
            'counties' => ['X'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E_B',
            'description' => 'B',
            'date' => new DateTimeImmutable('2026-06-02'),
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I_Z',
            'description' => 'Z',
            'date' => new DateTimeImmutable('2026-05-31'),
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I_A',
            'description' => 'A',
            'date' => new DateTimeImmutable('2026-06-01'),
            'counties' => ['X', 'Y'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I_C',
            'description' => 'C',
            'date' => new DateTimeImmutable('2026-06-02'),
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe(['E_A' => $imported[1], 'E_B' => $imported[2]])
        ->and($result->toInsert)->toBe([$imported[0]])
        ->and($result->toDelete)->toBe([]);
});

it('keeps an imported rename available after name pairing', function (): void {
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E_A',
            'description' => 'A',
            'date' => new DateTimeImmutable('2026-06-01'),
            'counties' => ['X'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E_B',
            'description' => 'B',
            'date' => new DateTimeImmutable('2026-06-02'),
            'counties' => ['Y'],
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I_C',
            'description' => 'C',
            'date' => new DateTimeImmutable('2026-06-02'),
            'counties' => ['Y', 'Z'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I_A',
            'description' => 'A',
            'date' => new DateTimeImmutable('2026-06-01'),
            'counties' => ['X', 'Y'],
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe(['E_A' => $imported[1], 'E_B' => $imported[0]])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('updates two of three same-name future holidays and deletes the leftover', function (): void {
    $date = new DateTimeImmutable('2026-12-25');
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'description' => 'Regional Day',
            'date' => $date,
            'counties' => ['X'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E2',
            'description' => 'Regional Day',
            'date' => $date,
            'counties' => ['Y'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E3',
            'description' => 'Regional Day',
            'date' => $date,
            'counties' => ['Z'],
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I1',
            'description' => 'Regional Day',
            'date' => $date,
            'counties' => ['X', 'A'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I2',
            'description' => 'Regional Day',
            'date' => $date,
            'counties' => ['Y', 'B'],
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe(['E1' => $imported[0], 'E2' => $imported[1]])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe(['E3']);
});

it('updates two of three same-name past holidays and keeps the leftover', function (): void {
    $date = new DateTimeImmutable('2026-04-06');
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'description' => 'Regional Day',
            'date' => $date,
            'counties' => ['X'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E2',
            'description' => 'Regional Day',
            'date' => $date,
            'counties' => ['Y'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E3',
            'description' => 'Regional Day',
            'date' => $date,
            'counties' => ['Z'],
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I1',
            'description' => 'Regional Day',
            'date' => $date,
            'counties' => ['X', 'A'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I2',
            'description' => 'Regional Day',
            'date' => $date,
            'counties' => ['Y', 'B'],
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe(['E1' => $imported[0], 'E2' => $imported[1]])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});

it('inserts a new holiday on a past date when the name is new and several remain', function (): void {
    $existing = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'description' => 'A',
            'date' => new DateTimeImmutable('2026-04-06'),
        ]),
    ];
    $imported = [
        PublicHolidayDTOFixture::create([
            'id' => 'I1',
            'description' => 'A',
            'date' => new DateTimeImmutable('2026-04-06'),
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I2',
            'description' => 'B',
            'date' => new DateTimeImmutable('2026-04-06'),
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'I3',
            'description' => 'C',
            'date' => new DateTimeImmutable('2026-04-06'),
        ]),
    ];

    $result = $this->reconciler->reconcile($existing, $imported, $this->today);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe([$imported[1], $imported[2]])
        ->and($result->toDelete)->toBe([]);
});

it('does not pair holidays with the same description on different dates', function (): void {
    $existingHoliday = PublicHolidayDTOFixture::create([
        'id' => 'E1',
        'description' => 'Bridge Day',
        'date' => new DateTimeImmutable('2026-08-14'),
    ]);
    $importedHoliday = PublicHolidayDTOFixture::create([
        'id' => 'I1',
        'description' => 'Bridge Day',
        'date' => new DateTimeImmutable('2026-08-15'),
    ]);

    $result = $this->reconciler->reconcile([$existingHoliday], [$importedHoliday], $this->today);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe([$importedHoliday])
        ->and($result->toDelete)->toBe(['E1']);
});

it('deletes only the unmatched one of two identical existing holidays', function (): void {
    $existingHolidays = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'description' => 'Second Day',
            'date' => new DateTimeImmutable('2026-12-26'),
            'counties' => ['CH-ZH'],
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E2',
            'description' => 'Second Day',
            'date' => new DateTimeImmutable('2026-12-26'),
            'counties' => ['CH-ZH'],
        ]),
    ];
    $importedHoliday = PublicHolidayDTOFixture::create([
        'id' => 'I1',
        'description' => 'Second Day',
        'date' => new DateTimeImmutable('2026-12-26'),
        'counties' => ['CH-ZH'],
    ]);

    $result = $this->reconciler->reconcile($existingHolidays, [$importedHoliday], $this->today);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toHaveCount(1)
        ->and($result->toDelete[0])->toBeIn(['E1', 'E2']);
});

it('deletes a missing holiday dated today even when the clock is later in the day', function (): void {
    $laterToday = new DateTimeImmutable('2026-06-15 18:30');
    $existingHoliday = PublicHolidayDTOFixture::create([
        'id' => 'E1',
        'date' => new DateTimeImmutable('2026-06-15 00:00'),
    ]);

    $result = $this->reconciler->reconcile([$existingHoliday], [], $laterToday);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe(['E1']);
});

it('pairs unmatched holidays per date and leaves ambiguous dates alone', function (): void {
    $existingPastRename = PublicHolidayDTOFixture::create([
        'id' => 'E1',
        'description' => 'Labour Day',
        'date' => new DateTimeImmutable('2026-05-01'),
    ]);
    $existingRegionChange = PublicHolidayDTOFixture::create([
        'id' => 'E2',
        'description' => 'Unity Day',
        'date' => new DateTimeImmutable('2026-10-03'),
    ]);
    $existingChristmas = PublicHolidayDTOFixture::create([
        'id' => 'E3',
        'description' => 'Christmas',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $existingBoxingDay = PublicHolidayDTOFixture::create([
        'id' => 'E4',
        'description' => 'Boxing Day',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);
    $importedWorkersDay = PublicHolidayDTOFixture::create([
        'id' => 'I1',
        'description' => 'Workers Day',
        'date' => new DateTimeImmutable('2026-05-01'),
    ]);
    $importedGermanUnity = PublicHolidayDTOFixture::create([
        'id' => 'I2',
        'description' => 'German Unity',
        'date' => new DateTimeImmutable('2026-10-03'),
    ]);
    $importedChristmasDay = PublicHolidayDTOFixture::create([
        'id' => 'I3',
        'description' => 'Christmas Day',
        'date' => new DateTimeImmutable('2026-12-25'),
    ]);

    $result = $this->reconciler->reconcile(
        [$existingPastRename, $existingRegionChange, $existingChristmas, $existingBoxingDay],
        [$importedWorkersDay, $importedGermanUnity, $importedChristmasDay],
        $this->today,
    );

    expect($result->toUpdate)->toBe(['E1' => $importedWorkersDay, 'E2' => $importedGermanUnity])
        ->and($result->toInsert)->toBe([$importedChristmasDay])
        ->and($result->toDelete)->toEqualCanonicalizing(['E3', 'E4']);
});

it('inserts new imported holidays', function (): void {
    $importedHoliday = PublicHolidayDTOFixture::create([
        'id' => 'I1',
        'date' => new DateTimeImmutable('2026-06-15'),
    ]);

    $result = $this->reconciler->reconcile([], [$importedHoliday], $this->today);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe([$importedHoliday])
        ->and($result->toDelete)->toBe([]);
});

it('deletes missing holidays dated today or later', function (): void {
    $existingHolidays = [
        PublicHolidayDTOFixture::create([
            'id' => 'E1',
            'date' => new DateTimeImmutable('2026-06-15'),
        ]),
        PublicHolidayDTOFixture::create([
            'id' => 'E2',
            'date' => new DateTimeImmutable('2026-12-25'),
        ]),
    ];

    $result = $this->reconciler->reconcile($existingHolidays, [], $this->today);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe(['E1', 'E2']);
});

it('keeps missing holidays dated before today', function (): void {
    $existingHoliday = PublicHolidayDTOFixture::create([
        'id' => 'E1',
        'date' => new DateTimeImmutable('2026-01-01'),
    ]);

    $result = $this->reconciler->reconcile([$existingHoliday], [], $this->today);

    expect($result->toUpdate)->toBe([])
        ->and($result->toInsert)->toBe([])
        ->and($result->toDelete)->toBe([]);
});
