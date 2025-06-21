<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;

interface HolidayFacadeInterface
{
    public function getHolidayCalendarForCountry(string $countryCode): PublicHolidayCalendarDTO;

    public function upsertHolidayCalendar(PublicHolidayCalendarDTO $publicHolidayCalendarDTO): void;

    /**
     * @return PublicHolidayDTO[]
     */
    public function getHolidayDaysForCountryBetweenDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $countryCode): array;

    /**
     * @return array{string, UserPublicHolidaysDTO}
     */
    public function getHolidaysForDatesGroupedByUserId(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array;
}
