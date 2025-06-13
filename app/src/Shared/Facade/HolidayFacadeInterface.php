<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;

interface HolidayFacadeInterface
{
    public function getHolidayCalendarForCountry(string $countryCode): PublicHolidayCalendarDTO;

    public function upsertHolidayCalendar(PublicHolidayCalendarDTO $publicHolidayCalendarDTO): void;
}
