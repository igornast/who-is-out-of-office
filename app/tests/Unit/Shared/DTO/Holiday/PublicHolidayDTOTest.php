<?php

declare(strict_types=1);

use App\Shared\DTO\DataNager\NagerPublicHolidayDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;

it('creates from array with regional fields', function () {
    $data = [
        'id' => 'holiday-1',
        'description' => 'Epiphany',
        'country_code' => 'DE',
        'date' => '2026-01-06',
        'is_global' => 0,
        'counties' => '["DE-BW","DE-BY","DE-ST"]',
    ];

    $dto = PublicHolidayDTO::fromArray($data);

    expect($dto->isGlobal)->toBeFalse()
        ->and($dto->counties)->toBe(['DE-BW', 'DE-BY', 'DE-ST']);
});

it('creates from array with global holiday', function () {
    $data = [
        'id' => 'holiday-2',
        'description' => 'New Year',
        'country_code' => 'DE',
        'date' => '2026-01-01',
        'is_global' => 1,
        'counties' => null,
    ];

    $dto = PublicHolidayDTO::fromArray($data);

    expect($dto->isGlobal)->toBeTrue()
        ->and($dto->counties)->toBeNull();
});

it('defaults regional fields when not present in array', function () {
    $data = [
        'id' => 'holiday-3',
        'description' => 'Old Holiday',
        'country_code' => 'DE',
        'date' => '2026-01-01',
    ];

    $dto = PublicHolidayDTO::fromArray($data);

    expect($dto->isGlobal)->toBeTrue()
        ->and($dto->counties)->toBeNull();
});

it('creates from nager DTO with regional fields', function () {
    $nagerDTO = new NagerPublicHolidayDTO(
        date: new DateTimeImmutable('2026-01-06'),
        localName: 'Heilige Drei Könige',
        name: 'Epiphany',
        global: false,
        counties: ['DE-BW', 'DE-BY', 'DE-ST'],
    );

    $dto = PublicHolidayDTO::fromNager($nagerDTO, 'DE');

    expect($dto->isGlobal)->toBeFalse()
        ->and($dto->counties)->toBe(['DE-BW', 'DE-BY', 'DE-ST'])
        ->and($dto->description)->toBe('Heilige Drei Könige')
        ->and($dto->countryCode)->toBe('DE');
});

it('creates from nager DTO with global holiday', function () {
    $nagerDTO = new NagerPublicHolidayDTO(
        date: new DateTimeImmutable('2026-01-01'),
        localName: 'Neujahr',
        name: 'New Year',
        global: true,
        counties: null,
    );

    $dto = PublicHolidayDTO::fromNager($nagerDTO, 'DE');

    expect($dto->isGlobal)->toBeTrue()
        ->and($dto->counties)->toBeNull();
});
