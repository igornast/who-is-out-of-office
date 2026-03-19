<?php

declare(strict_types=1);

namespace App\Shared\Handler\LeaveRequest\Query;

final readonly class CalculateWorkdaysQuery
{
    /**
     * @param int[] $userWorkingDays
     */
    public function __construct(
        public \DateTimeImmutable $startDate,
        public \DateTimeImmutable $endDate,
        public array $userWorkingDays,
        public ?string $holidayCalendarCountryCode,
        public ?string $subdivisionCode = null,
    ) {
    }
}
