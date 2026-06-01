<?php

declare(strict_types=1);

namespace App\Shared\DTO\CalendarSubscription;

use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;

class CalendarSubscriptionConfigDTO
{
    /**
     * @param list<CalendarSubscriptionCandidateDTO> $candidateTeamMembers
     * @param list<PublicHolidayCalendarDTO>         $candidateHolidayCalendars
     * @param list<string>                           $topLevelTeamMemberIds      ids the UI should render at the root of the team list (peers, manager, direct reports)
     * @param list<string>                           $myTeamMemberIds            the current user's direct reports (teammates whose managerId === currentUserId); may be empty
     * @param list<string>|null                      $selectedTeamMemberIds      null = auto (use top-level set)
     * @param list<string>|null                      $selectedHolidayCalendarIds null = auto (use all candidates)
     */
    public function __construct(
        public array $candidateTeamMembers,
        public array $candidateHolidayCalendars,
        public array $topLevelTeamMemberIds,
        public array $myTeamMemberIds,
        public ?array $selectedTeamMemberIds,
        public ?array $selectedHolidayCalendarIds,
    ) {
    }
}
