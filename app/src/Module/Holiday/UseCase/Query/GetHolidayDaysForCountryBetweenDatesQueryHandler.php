<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Query;

use App\Module\Holiday\Repository\PublicHolidayRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayDTO;

class GetHolidayDaysForCountryBetweenDatesQueryHandler
{
    public function __construct(
        private readonly PublicHolidayRepositoryInterface $publicHolidayRepository,
    ) {
    }

    /**
     * @return PublicHolidayDTO[]
     */
    public function handle(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $countryCode, ?string $subdivisionCode = null): array
    {
        return $this->publicHolidayRepository->findBetweenDatesForCountryCode($startDate, $endDate, $countryCode, $subdivisionCode);
    }
}
