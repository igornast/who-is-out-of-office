<?php

declare(strict_types=1);

namespace App\Shared\DTO\Dashboard;

class DailyAbsenceSummaryDTO
{
    /**
     * @param array<array{firstName: string, lastName: string, profileImageUrl: ?string, leaveTypeBackgroundColor: string}> $avatars
     */
    public function __construct(
        public \DateTimeImmutable $date,
        public string $dayName,
        public int $dayNumber,
        public bool $isToday,
        public int $absenceCount,
        public array $avatars,
    ) {
    }
}
