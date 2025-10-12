<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Handler\LeaveRequest\Query\CalculateWorkdaysQuery;

class CalculateWorkDaysQueryHandler
{
    public function __construct(
        private readonly HolidayFacadeInterface $holidayFacade,
    ) {
    }

    public function handle(CalculateWorkdaysQuery $query): int
    {
        if ($query->startDate > $query->endDate) {
            return 0;
        }

        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($query->startDate, $interval, $query->endDate->modify('+1 day'));
        $workDays = 0;

        $holidayDates = [];

        if (null !== $query->holidayCalendarCountryCode) {
            $holidayDTOs = $this->holidayFacade->getHolidayDaysForCountryBetweenDates(
                $query->startDate,
                $query->endDate,
                $query->holidayCalendarCountryCode
            );
            $holidayDates = array_map(
                fn ($h) => $h->date->format('Y-m-d'),
                $holidayDTOs
            );
        }

        foreach ($period as $day) {
            $isWorkday = in_array((int) $day->format('N'), $query->userWorkingDays);
            $isHoliday = in_array($day->format('Y-m-d'), $holidayDates);

            if ($isWorkday && !$isHoliday) {
                ++$workDays;
            }
        }

        return $workDays;
    }
}
