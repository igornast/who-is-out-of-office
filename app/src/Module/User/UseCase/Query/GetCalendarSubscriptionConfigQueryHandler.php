<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\UserRepositoryInterface;
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

        $countryCodes = array_filter(
            [$user->calendarCountryCode, ...array_map(fn (UserDTO $u) => $u->calendarCountryCode, $teammates)],
            fn (?string $code): bool => null !== $code && '' !== $code,
        )
                |> array_unique(...)
                |> array_values(...);

        $calendars = $this->holidayFacade->getActiveCalendarsForCountryCodes($countryCodes);

        return new CalendarSubscriptionConfigDTO(
            candidateTeamMembers: array_values($teammates),
            candidateHolidayCalendars: array_values($calendars),
            selectedTeamMemberIds: $user->calendarSubscriptionTeamMemberIds,
            selectedHolidayCalendarIds: $user->calendarSubscriptionHolidayCalendarIds,
        );
    }
}
