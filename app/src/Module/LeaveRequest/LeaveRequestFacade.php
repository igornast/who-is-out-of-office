<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest;

use App\Module\LeaveRequest\UseCase\Query\GetCalculateWorkDaysHandler;
use App\Shared\Facade\LeaveRequestFacadeInterface;

final class LeaveRequestFacade implements LeaveRequestFacadeInterface
{
    public function __construct(
        private readonly GetCalculateWorkDaysHandler $workDaysHandler,
    ) {
    }

    public function calculateWorkDays(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int
    {
        return $this->workDaysHandler->handle($startDate, $endDate);
    }
}
