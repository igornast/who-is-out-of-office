<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\LeavePeriodRepositoryInterface;

class CloseLeavePeriodCommandHandler
{
    public function __construct(private readonly LeavePeriodRepositoryInterface $leavePeriodRepository)
    {
    }

    public function handle(string $userId, int $closingBalance): void
    {
        $openPeriod = $this->leavePeriodRepository->findOpenForUser($userId);

        if (null === $openPeriod) {
            return;
        }

        $this->leavePeriodRepository->close($openPeriod->id, $closingBalance, new \DateTimeImmutable());
    }
}
