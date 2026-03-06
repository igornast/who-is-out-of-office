<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Command;

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\Facade\DateNagerInterface;

class SyncCalendarCommandHandler
{
    public function __construct(
        private readonly DateNagerInterface $dateNager,
        private readonly PublicHolidayCalendarRepositoryInterface $calendarRepository,
    ) {
    }

    public function handle(string $countryCode, string $countryName, int $year): void
    {
        $holidays = $this->dateNager->getHolidaysForCountry($countryCode, $year);
        $calendarDTO = PublicHolidayCalendarDTO::createFromNager($countryCode, $countryName, $holidays);
        $this->calendarRepository->upsertByCountryCode($calendarDTO, $year);
    }
}
