<?php

declare(strict_types=1);

namespace App\Module\Holiday\UseCase\Query;

use App\Module\Holiday\Repository\PublicHolidayCalendarRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;

class GetActiveCalendarsForCountryCodesQueryHandler
{
    public function __construct(
        private readonly PublicHolidayCalendarRepositoryInterface $calendarRepository,
    ) {
    }

    /**
     * @param list<string|null> $countryCodes
     *
     * @return PublicHolidayCalendarDTO[]
     */
    public function handle(array $countryCodes): array
    {
        $filtered = array_values(array_filter(
            $countryCodes,
            fn (?string $code): bool => null !== $code && '' !== $code,
        ));

        if ([] === $filtered) {
            return [];
        }

        return $this->calendarRepository->findActiveByCountryCodes($filtered);
    }
}
