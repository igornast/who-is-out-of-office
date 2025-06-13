<?php

declare(strict_types=1);

namespace App\Module\Holiday;

use App\Module\Holiday\UseCase\Command\UpsertHolidayCalendarCommandHandler;
use App\Module\Holiday\UseCase\Query\GetHolidayCalendarForCountryCommandHandler;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\Facade\HolidayFacadeInterface;

final class HolidayFacade implements HolidayFacadeInterface
{
    public function __construct(
        private readonly UpsertHolidayCalendarCommandHandler $upsertHandler,
        private readonly GetHolidayCalendarForCountryCommandHandler $holidayCalendarHandler,
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
}
