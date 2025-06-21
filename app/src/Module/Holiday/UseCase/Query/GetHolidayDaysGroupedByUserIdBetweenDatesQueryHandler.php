<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Query;

use App\Module\Holiday\Repository\PublicHolidayRepositoryInterface;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;

class GetHolidayDaysGroupedByUserIdBetweenDatesQueryHandler
{
    public function __construct(
        private readonly PublicHolidayRepositoryInterface $publicHolidayRepository,
    ) {
    }

    /**
     * @return array{string, UserPublicHolidaysDTO}
     */
    public function handle(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->publicHolidayRepository->findBetweenDatesGroupedByUser($startDate, $endDate);
    }
}
