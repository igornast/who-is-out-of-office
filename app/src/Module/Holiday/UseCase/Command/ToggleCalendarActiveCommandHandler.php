<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Command;

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;

class ToggleCalendarActiveCommandHandler
{
    public function __construct(
        private readonly PublicHolidayCalendarRepositoryInterface $calendarRepository,
    ) {
    }

    public function handle(string $calendarId, bool $isActive): void
    {
        $this->calendarRepository->updateActive($calendarId, $isActive);
    }
}
