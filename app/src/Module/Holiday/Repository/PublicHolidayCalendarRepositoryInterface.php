<?php

declare(strict_types=1);

namespace App\Module\Holiday\Repository;

use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;

interface PublicHolidayCalendarRepositoryInterface
{
    public function findByCountryCode(string $countryCode): ?PublicHolidayCalendarDTO;

    public function upsertByCountryCode(PublicHolidayCalendarDTO $calendarDTO, ?int $year = null): void;

    /**
     * @return PublicHolidayCalendarDTO[]
     */
    public function findAll(): array;

    public function updateActive(string $calendarId, bool $isActive): void;

    public function delete(string $calendarId): void;

    public function hasAssignedUsers(string $calendarId): bool;

    public function deleteHolidaysForYear(string $calendarId, int $year): void;
}
