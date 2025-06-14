<?php

declare(strict_types=1);

namespace App\Module\Holiday;

use App\Module\Holiday\UseCase\Command\UpsertHolidayCalendarCommandHandler;
use App\Module\Holiday\UseCase\Query\GetHolidayCalendarForCountryCommandHandler;
use App\Module\Holiday\UseCase\Query\GetHolidayDaysForCountryBetweenDatesQueryHandler;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\Facade\HolidayFacadeInterface;

final class HolidayFacade implements HolidayFacadeInterface
{
    public function __construct(
        private readonly UpsertHolidayCalendarCommandHandler $upsertHandler,
        private readonly GetHolidayCalendarForCountryCommandHandler $holidayCalendarHandler,
        private readonly GetHolidayDaysForCountryBetweenDatesQueryHandler $holidayDaysHandler,
    ) {
    }

    public function getHolidayCalendarForCountry(string $countryCode): PublicHolidayCalendarDTO
    {
        return $this->holidayCalendarHandler->handle($countryCode);
    }

    public function upsertHolidayCalendar(PublicHolidayCalendarDTO $publicHolidayCalendarDTO): void
    {
        $this->upsertHandler->handle($publicHolidayCalendarDTO);
    }

    /**
     * @return PublicHolidayDTO[]
     */
    public function getHolidayDaysForCountryBetweenDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $countryCode): array
    {
        return $this->holidayDaysHandler->handle($startDate, $endDate, $countryCode);
    }
}
