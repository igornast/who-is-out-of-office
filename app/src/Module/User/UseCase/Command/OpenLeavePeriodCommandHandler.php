<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Shared\DTO\Balance\LeavePeriodDTO;
use App\Shared\DTO\UserDTO;
use Ramsey\Uuid\Uuid;

class OpenLeavePeriodCommandHandler
{
    public function __construct(private readonly LeavePeriodRepositoryInterface $leavePeriodRepository)
    {
    }

    public function handle(
        UserDTO $userDTO,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        int $openingBalance,
    ): LeavePeriodDTO {
        $leavePeriodDTO = new LeavePeriodDTO(
            id: Uuid::uuid4()->toString(),
            userId: $userDTO->id,
            allowanceGranted: $userDTO->annualLeaveAllowance,
            openingBalance: $openingBalance,
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->leavePeriodRepository->add($leavePeriodDTO);

        return $leavePeriodDTO;
    }
}
