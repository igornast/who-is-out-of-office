<?php

declare(strict_types=1);

namespace App\Shared\DTO\CalendarSubscription;

use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\UserDTO;

class CalendarSubscriptionConfigDTO
{
    /**
     * @param list<UserDTO>                  $candidateTeamMembers
     * @param list<PublicHolidayCalendarDTO> $candidateHolidayCalendars
     * @param list<string>|null              $selectedTeamMemberIds      null = auto (use all candidates)
     * @param list<string>|null              $selectedHolidayCalendarIds null = auto (use all candidates)
     */
    public function __construct(
        public array $candidateTeamMembers,
        public array $candidateHolidayCalendars,
        public ?array $selectedTeamMemberIds,
        public ?array $selectedHolidayCalendarIds,
    ) {
    }
}
