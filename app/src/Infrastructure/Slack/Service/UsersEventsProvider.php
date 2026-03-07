<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Service;

use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use Illuminate\Support\Collection;

class UsersEventsProvider
{
    public function __construct(
        private readonly HolidayFacadeInterface $holidayFacade,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    /**
     * @return array{string, LeaveRequestDTO|UserPublicHolidaysDTO}|void[]
     */
    public function provideMergedAbsencesPerUser(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        /** @var array{string, LeaveRequestDTO[]} $mapUserIdToApprovedRequests */
        $mapUserIdToApprovedRequests = $this->leaveRequestFacade->getLeaveRequestsForDatesGroupedByUserId($startDate, $endDate, [LeaveRequestStatusEnum::Approved]);
        /** @var array{string, UserPublicHolidaysDTO} $mapUserIdToHolidays */
        $mapUserIdToHolidays = $this->holidayFacade->getHolidaysForDatesGroupedByUserId($startDate, $endDate);

        /** @var Collection<string, UserPublicHolidaysDTO|LeaveRequestDTO> $events */
        $events = collect($mapUserIdToApprovedRequests)
            ->union($mapUserIdToHolidays)
            ->map(function ($item, $key) use ($mapUserIdToApprovedRequests, $mapUserIdToHolidays) {
                $publicHolidays = $mapUserIdToHolidays[$key] ?? null;

                if (!$publicHolidays instanceof UserPublicHolidaysDTO) {
                    return $mapUserIdToApprovedRequests[$key];
                }

                return array_merge($mapUserIdToApprovedRequests[$key] ?? [], [$publicHolidays]);
            });

        return $events->toArray();
    }

    /**
     * @param array<string, UserPublicHolidaysDTO> $mapUserIdToHolidays
     *
     * @return array<string, UserPublicHolidaysDTO>
     */
    public function filterWeekendHolidays(array $mapUserIdToHolidays): array
    {
        $filtered = [];

        foreach ($mapUserIdToHolidays as $userId => $dto) {
            $weekdayHolidays = array_values(array_filter(
                $dto->holidays,
                fn (PublicHolidayDTO $holiday) => (int) $holiday->date->format('N') < 6,
            ));

            if ([] === $weekdayHolidays) {
                continue;
            }

            $filtered[$userId] = new UserPublicHolidaysDTO($dto->user, $weekdayHolidays);
        }

        return $filtered;
    }
}
