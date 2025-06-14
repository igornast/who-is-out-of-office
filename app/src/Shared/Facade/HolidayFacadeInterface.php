<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;

interface HolidayFacadeInterface
{
    public function getHolidayCalendarForCountry(string $countryCode): PublicHolidayCalendarDTO;

    public function upsertHolidayCalendar(PublicHolidayCalendarDTO $publicHolidayCalendarDTO): void;

    /**
     * @return PublicHolidayDTO[]
     */
    public function getHolidayDaysForCountryBetweenDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $countryCode): array;
}
