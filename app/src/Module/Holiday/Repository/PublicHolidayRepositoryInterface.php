<?php

declare(strict_types=1);

namespace App\Module\Holiday\Repository;

interface PublicHolidayRepositoryInterface
{
    public function findBetweenDatesForCountryCode(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $countryCode): array;
}
