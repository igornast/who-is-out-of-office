<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;

interface HolidayFacadeInterface
{
    public function getHolidayCalendarForCountry(string $countryCode): PublicHolidayCalendarDTO;

    public function upsertHolidayCalendar(PublicHolidayCalendarDTO $publicHolidayCalendarDTO): void;

    /**
     * @return PublicHolidayDTO[]
     */
    public function getHolidayDaysForCountryBetweenDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, string $countryCode, ?string $subdivisionCode = null): array;

    /**
     * @return array{string, UserPublicHolidaysDTO}
     */
    public function getHolidaysForDatesGroupedByUserId(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array;

    /**
     * @return PublicHolidayCalendarDTO[]
     */
    public function getAllCalendars(): array;

    public function toggleCalendarActive(string $calendarId, bool $isActive): void;

    public function syncCalendar(string $countryCode, string $countryName, int $year): void;

    public function syncAllActiveCalendars(int $year): void;

    public function deleteCalendar(string $calendarId): void;

    /**
     * @return array<string, string[]> Map of calendar ID to sorted subdivision codes
     */
    public function getSubdivisionsGroupedByCalendar(): array;
}
