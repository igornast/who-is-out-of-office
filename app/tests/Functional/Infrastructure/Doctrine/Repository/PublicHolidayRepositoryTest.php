<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Repository\PublicHolidayRepository;

beforeEach(function (): void {
    static::bootKernel();
    $this->repository = static::getContainer()->get(PublicHolidayRepository::class);
    $this->yearStart = new DateTimeImmutable(date('Y').'-01-01');
    $this->yearEnd = new DateTimeImmutable(date('Y').'-12-31');
});

it('returns global and matching regional holidays for subdivision code', function (): void {
    $results = $this->repository->findBetweenDatesForCountryCode(
        $this->yearStart,
        $this->yearEnd,
        'DE',
        'DE-BY',
    );

    $descriptions = array_map(fn ($dto) => $dto->description, $results);

    expect($descriptions)->toContain('Tag der Deutschen Einheit')
        ->and($descriptions)->toContain('Heilige Drei Könige')
        ->and($descriptions)->not->toContain('Mariä Himmelfahrt');
});

it('excludes regional holidays not matching subdivision code', function (): void {
    $results = $this->repository->findBetweenDatesForCountryCode(
        $this->yearStart,
        $this->yearEnd,
        'DE',
        'DE-SL',
    );

    $descriptions = array_map(fn ($dto) => $dto->description, $results);

    expect($descriptions)->toContain('Tag der Deutschen Einheit')
        ->and($descriptions)->toContain('Mariä Himmelfahrt')
        ->and($descriptions)->not->toContain('Heilige Drei Könige');
});

it('returns all holidays when no subdivision code is provided', function (): void {
    $results = $this->repository->findBetweenDatesForCountryCode(
        $this->yearStart,
        $this->yearEnd,
        'DE',
    );

    $descriptions = array_map(fn ($dto) => $dto->description, $results);

    expect($descriptions)->toContain('Tag der Deutschen Einheit')
        ->and($descriptions)->toContain('Heilige Drei Könige')
        ->and($descriptions)->toContain('Mariä Himmelfahrt');
});
