<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Query;

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;

class GetAllCalendarsQueryHandler
{
    public function __construct(
        private readonly PublicHolidayCalendarRepositoryInterface $calendarRepository,
    ) {
    }

    /**
     * @return PublicHolidayCalendarDTO[]
     */
    public function handle(): array
    {
        return $this->calendarRepository->findAll();
    }
}
