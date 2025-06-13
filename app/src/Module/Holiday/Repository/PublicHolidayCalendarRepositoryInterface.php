<?php

declare(strict_types=1);

namespace App\Module\Holiday\Repository;

use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;

interface PublicHolidayCalendarRepositoryInterface
{
    public function findByCountryCode(string $countryCode): ?PublicHolidayCalendarDTO;

    public function upsertByCountryCode(PublicHolidayCalendarDTO $calendarDTO): void;
}
