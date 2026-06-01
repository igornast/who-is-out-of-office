<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\CalendarSubscription\CalendarSubscriptionCandidateDTO;
use App\Shared\DTO\CalendarSubscription\CalendarSubscriptionConfigDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Facade\HolidayFacadeInterface;

class GetCalendarSubscriptionConfigQueryHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly HolidayFacadeInterface $holidayFacade,
    ) {
    }

    public function handle(string $userId): CalendarSubscriptionConfigDTO
    {
        $user = $this->userRepository->findOneById($userId);

        if (null === $user) {
            throw new \RuntimeException(sprintf('User %s not found', $userId));
        }

        $teammates = $this->userRepository->findTeammatesOf($userId);
        $descendants = $this->userRepository->findManagementDescendants($userId);

        $countryCodes = array_filter(
            [
                $user->calendarCountryCode,
                ...array_map(fn (UserDTO $u) => $u->calendarCountryCode, $teammates),
                ...array_map(fn (UserDTO $u) => $u->calendarCountryCode, $descendants),
            ],
            fn (?string $code): bool => null !== $code && '' !== $code,
        )
            |> array_unique(...)
            |> array_values(...);

        $calendars = $this->holidayFacade->getActiveCalendarsForCountryCodes($countryCodes);

        /** @var array<string, UserDTO> $usersById */
        $usersById = [];
        foreach ([...$teammates, ...$descendants] as $u) {
            $usersById[$u->id] = $u;
        }

        $reportsByManager = [];
        foreach ($usersById as $u) {
            if (null !== $u->managerId && isset($usersById[$u->managerId])) {
                $reportsByManager[$u->managerId][] = $u->id;
            }
        }

        $candidates = [];
        foreach ($usersById as $u) {
            $reportIds = $reportsByManager[$u->id] ?? [];
            $candidates[] = new CalendarSubscriptionCandidateDTO(
                id: $u->id,
                name: $u->getFullName(),
                email: $u->email,
                initials: mb_strtoupper(mb_substr($u->firstName, 0, 1).mb_substr($u->lastName, 0, 1)),
                colorIndex: mb_strlen($u->firstName.$u->lastName) % 6,
                isManager: [] !== $reportIds,
                reportIds: $reportIds,
                profileImageUrl: $u->profileImageUrl,
            );
        }

        $topLevelIds = array_values(array_unique(array_map(fn (UserDTO $u) => $u->id, $teammates)));

        return new CalendarSubscriptionConfigDTO(
            candidateTeamMembers: $candidates,
            candidateHolidayCalendars: array_values($calendars),
            topLevelTeamMemberIds: $topLevelIds,
            selectedTeamMemberIds: $user->calendarSubscriptionTeamMemberIds,
            selectedHolidayCalendarIds: $user->calendarSubscriptionHolidayCalendarIds,
        );
    }
}
