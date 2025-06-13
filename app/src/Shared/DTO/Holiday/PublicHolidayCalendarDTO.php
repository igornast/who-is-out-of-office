<?php

declare(strict_types=1);

namespace App\Shared\DTO\Holiday;

use App\Infrastructure\Doctrine\Entity\Holiday;
use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Shared\DTO\DataNager\NagerPublicHolidayDto;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

readonly class PublicHolidayCalendarDTO
{
    /**
     * @param PublicHolidayDTO[] $holidays
     */
    public function __construct(
        public UuidInterface $id,
        public string $countryCode,
        public string $countryName,
        public array $holidays,
    ) {
    }

    /**
     * @param NagerPublicHolidayDto[] $holidays
     */
    public static function createFromNager(string $countryCode, string $countryName, array $holidays): self
    {
        return new self(
            id: Uuid::uuid4(),
            countryCode: $countryCode,
            countryName: $countryName,
            holidays: array_map(fn (NagerPublicHolidayDto $holiday) => PublicHolidayDTO::fromNager($holiday), $holidays),
        );
    }

    public static function fromEntity(HolidayCalendar $holidayCalendar): self
    {
        return new self(
            id: $holidayCalendar->id,
            countryCode: $holidayCalendar->countryCode,
            countryName: $holidayCalendar->countryName,
            holidays: array_map(
                fn (Holiday $holiday) => PublicHolidayDTO::fromEntity($holiday),
                $holidayCalendar->holidays->toArray()
            ),
        );
    }
}
