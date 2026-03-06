<?php

declare(strict_types=1);

namespace App\Module\Holiday;

use App\Module\Holiday\UseCase\Command\DeleteCalendarCommandHandler;
use App\Module\Holiday\UseCase\Command\SyncAllActiveCalendarsCommandHandler;
use App\Module\Holiday\UseCase\Command\SyncCalendarCommandHandler;
use App\Module\Holiday\UseCase\Command\ToggleCalendarActiveCommandHandler;
use App\Module\Holiday\UseCase\Command\UpsertHolidayCalendarCommandHandler;
use App\Module\Holiday\UseCase\Query\GetAllCalendarsQueryHandler;
use App\Module\Holiday\UseCase\Query\GetHolidayCalendarForCountryQueryHandler;
use App\Module\Holiday\UseCase\Query\GetHolidayDaysForCountryBetweenDatesQueryHandler;
use App\Module\Holiday\UseCase\Query\GetHolidayDaysGroupedByUserIdBetweenDatesQueryHandler;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;
use App\Shared\Facade\HolidayFacadeInterface;

final class HolidayFacade implements HolidayFacadeInterface
{
    public function __construct(
        private readonly UpsertHolidayCalendarCommandHandler $upsertHandler,
        private readonly GetHolidayCalendarForCountryQueryHandler $holidayCalendarHandler,
        private readonly GetHolidayDaysForCountryBetweenDatesQueryHandler $holidayDaysHandler,
        private readonly GetHolidayDaysGroupedByUserIdBetweenDatesQueryHandler $holidayDaysGroupedByUserIdHandler,
        private readonly GetAllCalendarsQueryHandler $getAllCalendarsHandler,
        private readonly ToggleCalendarActiveCommandHandler $toggleCalendarActiveHandler,
        private readonly SyncCalendarCommandHandler $syncCalendarHandler,
        private readonly SyncAllActiveCalendarsCommandHandler $syncAllActiveCalendarsHandler,
        private readonly DeleteCalendarCommandHandler $deleteCalendarHandler,
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

    /**
     * @return array{string, UserPublicHolidaysDTO}
     */
    public function getHolidaysForDatesGroupedByUserId(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->holidayDaysGroupedByUserIdHandler->handle($startDate, $endDate);
    }

    /**
     * @return PublicHolidayCalendarDTO[]
     */
    public function getAllCalendars(): array
    {
        return $this->getAllCalendarsHandler->handle();
    }

    public function toggleCalendarActive(string $calendarId, bool $isActive): void
    {
        $this->toggleCalendarActiveHandler->handle($calendarId, $isActive);
    }

    public function syncCalendar(string $countryCode, string $countryName, int $year): void
    {
        $this->syncCalendarHandler->handle($countryCode, $countryName, $year);
    }

    public function syncAllActiveCalendars(int $year): void
    {
        $this->syncAllActiveCalendarsHandler->handle($year);
    }

    public function deleteCalendar(string $calendarId): void
    {
        $this->deleteCalendarHandler->handle($calendarId);
    }
}
