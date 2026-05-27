<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Query;

use App\Module\Holiday\Repository\PublicHolidayRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayDTO;

class GetHolidaysForCalendarsBetweenQueryHandler
{
    public function __construct(
        private readonly PublicHolidayRepositoryInterface $publicHolidayRepository,
    ) {
    }

    /**
     * @param list<string> $calendarIds
     *
     * @return PublicHolidayDTO[]
     */
    public function handle(array $calendarIds, \DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->publicHolidayRepository->findBetweenDatesForCalendarIds($calendarIds, $startDate, $endDate);
    }
}
