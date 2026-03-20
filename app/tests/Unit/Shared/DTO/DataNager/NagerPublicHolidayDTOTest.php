<?php

declare(strict_types=1);

use App\Shared\DTO\DataNager\NagerPublicHolidayDTO;

it('creates from array with global and counties fields', function () {
    $data = [
        'date' => '2026-01-06',
        'localName' => 'Heilige Drei Könige',
        'name' => 'Epiphany',
        'global' => false,
        'counties' => ['DE-BW', 'DE-BY', 'DE-ST'],
    ];

    $dto = NagerPublicHolidayDTO::fromArray($data);

    expect($dto->date->format('Y-m-d'))->toBe('2026-01-06')
        ->and($dto->localName)->toBe('Heilige Drei Könige')
        ->and($dto->name)->toBe('Epiphany')
        ->and($dto->global)->toBeFalse()
        ->and($dto->counties)->toBe(['DE-BW', 'DE-BY', 'DE-ST']);
});

it('creates from array with global national holiday', function () {
    $data = [
        'date' => '2026-01-01',
        'localName' => 'Neujahr',
        'name' => 'New Year',
        'global' => true,
        'counties' => null,
    ];

    $dto = NagerPublicHolidayDTO::fromArray($data);

    expect($dto->global)->toBeTrue()
        ->and($dto->counties)->toBeNull();
});

it('defaults global to true and counties to null when not present in array', function () {
    $data = [
        'date' => '2026-01-01',
        'localName' => 'New Year',
        'name' => 'New Year',
    ];

    $dto = NagerPublicHolidayDTO::fromArray($data);

    expect($dto->global)->toBeTrue()
        ->and($dto->counties)->toBeNull();
});
