<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Shared\Facade\HolidayFacadeInterface;

class GetCalculateWorkDaysQueryHandler
{
    public function __construct(
        private readonly HolidayFacadeInterface $holidayFacade,
    ) {
    }

    public function handle(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, ?string $holidayCalendarCountryCode): int
    {
        if ($startDate > $endDate) {
            return 0;
        }

        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
        $workDays = 0;

        $holidayDates = [];

        if (null !== $holidayCalendarCountryCode) {
            $holidayDTOs = $this->holidayFacade->getHolidayDaysForCountryBetweenDates($startDate, $endDate, $holidayCalendarCountryCode);
            $holidayDates = array_map(
                fn ($h) => $h->date->format('Y-m-d'),
                $holidayDTOs
            );
        }

        foreach ($period as $day) {
            $isWeekend = (int) $day->format('N') >= 6;
            $isHoliday = in_array($day->format('Y-m-d'), $holidayDates);

            if (!$isWeekend && !$isHoliday) {
                ++$workDays;
            }
        }

        return $workDays;
    }
}
