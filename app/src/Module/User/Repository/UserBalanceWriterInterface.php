<?php

declare(strict_types=1);

namespace App\Module\User\Repository;

interface UserBalanceWriterInterface
{
    public function updateCurrentLeaveBalance(string $userId, int $currentLeaveBalance): void;

    public function updateAbsenceBalanceResetDay(string $userId, \DateTimeImmutable $absenceBalanceResetDay): void;
}
