<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Command;

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;

class SyncAllActiveCalendarsCommandHandler
{
    public function __construct(
        private readonly PublicHolidayCalendarRepositoryInterface $calendarRepository,
        private readonly SyncCalendarCommandHandler $syncCalendarHandler,
    ) {
    }

    public function handle(int $year): void
    {
        $calendars = $this->calendarRepository->findAll();

        foreach ($calendars as $calendar) {
            if (!$calendar->isActive) {
                continue;
            }

            $this->syncCalendarHandler->handle($calendar->countryCode, $calendar->countryName, $year);
        }
    }
}
