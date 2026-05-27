<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Query\GetCalendarSubscriptionConfigQueryHandler;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\DTO\UserDTO;

class UpdateCalendarSubscriptionConfigCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly GetCalendarSubscriptionConfigQueryHandler $configHandler,
    ) {
    }

    /**
     * @param list<string>|null $teamMemberIds
     * @param list<string>|null $holidayCalendarIds
     */
    public function handle(string $userId, ?array $teamMemberIds, ?array $holidayCalendarIds): void
    {
        if (null === $teamMemberIds && null === $holidayCalendarIds) {
            $this->userRepository->updateCalendarSubscriptionConfig($userId, null, null);

            return;
        }

        $config = $this->configHandler->handle($userId);

        $allowedTeamIds = array_map(fn (UserDTO $u) => $u->id, $config->candidateTeamMembers);
        $allowedCalendarIds = array_map(fn (PublicHolidayCalendarDTO $c) => $c->id->toString(), $config->candidateHolidayCalendars);

        $filteredTeam = null === $teamMemberIds
            ? null
            : array_values(array_filter($teamMemberIds, fn (string $id) => in_array($id, $allowedTeamIds, true)));

        $filteredCalendars = null === $holidayCalendarIds
            ? null
            : array_values(array_filter($holidayCalendarIds, fn (string $id) => in_array($id, $allowedCalendarIds, true)));

        $this->userRepository->updateCalendarSubscriptionConfig($userId, $filteredTeam, $filteredCalendars);
    }
}
