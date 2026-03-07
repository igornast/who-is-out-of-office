<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Command;

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;

class DeleteCalendarCommandHandler
{
    public function __construct(
        private readonly PublicHolidayCalendarRepositoryInterface $calendarRepository,
    ) {
    }

    public function handle(string $calendarId): void
    {
        if ($this->calendarRepository->hasAssignedUsers($calendarId)) {
            throw new \RuntimeException(sprintf('Cannot delete calendar %s: it has assigned users', $calendarId));
        }

        $this->calendarRepository->delete($calendarId);
    }
}
