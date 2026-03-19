<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Query;

use App\Module\Holiday\Repository\PublicHolidayRepositoryInterface;

class GetSubdivisionsGroupedByCalendarQueryHandler
{
    public function __construct(
        private readonly PublicHolidayRepositoryInterface $publicHolidayRepository,
    ) {
    }

    /**
     * @return array<string, string[]>
     */
    public function handle(): array
    {
        return $this->publicHolidayRepository->findDistinctSubdivisionsGroupedByCalendar();
    }
}
