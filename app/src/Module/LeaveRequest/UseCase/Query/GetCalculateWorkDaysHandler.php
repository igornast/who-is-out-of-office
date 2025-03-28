<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

class GetCalculateWorkDaysHandler
{
    public function handle(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int
    {
        if ($startDate > $endDate) {
            return 0;
        }

        $interval = new \DateInterval('P1D');
        $period = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
        $workDays = 0;

        foreach ($period as $day) {
            if ((int) $day->format('N') < 6) {
                ++$workDays;
            }
        }

        return $workDays;
    }
}
