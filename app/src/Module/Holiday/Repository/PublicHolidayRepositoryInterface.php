<?php

declare(strict_types=1);

namespace App\Module\Holiday\Repository;

use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;

interface PublicHolidayRepositoryInterface
{
    /**
     * @return PublicHolidayDTO[]
     */
    public function findBetweenDatesForCountryCode(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $countryCode, ?string $subdivisionCode = null): array;

    /**
     * @return array{string, UserPublicHolidaysDTO}
     */
    public function findBetweenDatesGroupedByUser(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array;

    /**
     * @return array<string, string[]> Map of calendar ID to sorted subdivision codes
     */
    public function findDistinctSubdivisionsGroupedByCalendar(): array;
}
