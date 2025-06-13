<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Command;

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;

class UpsertHolidayCalendarCommandHandler
{
    public function __construct(
        private readonly PublicHolidayCalendarRepositoryInterface $calendarRepository,
    ) {
    }

    public function handle(PublicHolidayCalendarDTO $publicHolidayCalendarDTO): void
    {
        $this->calendarRepository->upsertByCountryCode($publicHolidayCalendarDTO);
    }
}
