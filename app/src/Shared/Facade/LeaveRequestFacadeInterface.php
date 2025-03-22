<?php

declare(strict_types=1);

namespace App\Shared\Facade;

interface LeaveRequestFacadeInterface
{
    public function calculateWorkDays(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): int;
}
