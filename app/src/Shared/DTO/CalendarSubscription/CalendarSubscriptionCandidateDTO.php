<?php

declare(strict_types=1);

namespace App\Shared\DTO\CalendarSubscription;

class CalendarSubscriptionCandidateDTO
{
    /**
     * @param list<string> $reportIds
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $initials,
        public int $colorIndex,
        public bool $isManager,
        public array $reportIds,
        public ?string $profileImageUrl = null,
    ) {
    }
}
