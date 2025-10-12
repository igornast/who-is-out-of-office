<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Query;

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;

class GetHolidayCalendarForCountryQueryHandler
{
    public function __construct(
        private PublicHolidayCalendarRepositoryInterface $calendarRepo,
    ) {
    }

    public function handle(string $countryCode): PublicHolidayCalendarDTO
    {
        $calendar = $this->calendarRepo->findByCountryCode($countryCode);

        if (!$calendar) {
            throw new \RuntimeException("Holiday calendar for country '$countryCode' not found.");
        }

        return $calendar;
    }
}
