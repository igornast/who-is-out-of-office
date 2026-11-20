<?php

declare(strict_types=1);

namespace App\Module\Holiday\Repository;

use App\Shared\DTO\Holiday\HolidayReconciliationDTO;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\Holiday\PublicHolidayDTO;

interface PublicHolidayCalendarRepositoryInterface
{
    public function findByCountryCode(string $countryCode): ?PublicHolidayCalendarDTO;

    /**
     * @return list<PublicHolidayDTO>
     */
    public function findHolidaysForYear(string $countryCode, int $year): array;

    public function applyHolidayReconciliation(PublicHolidayCalendarDTO $calendarDTO, int $year, HolidayReconciliationDTO $reconciliation): void;

    /**
     * @return PublicHolidayCalendarDTO[]
     */
    public function findAll(): array;

    public function updateActive(string $calendarId, bool $isActive): void;

    public function delete(string $calendarId): void;

    public function hasAssignedUsers(string $calendarId): bool;

    /**
     * @param list<string> $countryCodes
     *
     * @return PublicHolidayCalendarDTO[]
     */
    public function findActiveByCountryCodes(array $countryCodes): array;
}
