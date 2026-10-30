<?php

declare(strict_types=1);

namespace App\Module\User\Repository;

use App\Shared\DTO\Balance\LeavePeriodDTO;

interface LeavePeriodRepositoryInterface
{
    public function findOpenForUser(string $userId): ?LeavePeriodDTO;

    public function findOneById(string $id): ?LeavePeriodDTO;

    /**
     * @return LeavePeriodDTO[]
     */
    public function findAllForUser(string $userId): array;

    public function add(LeavePeriodDTO $leavePeriodDTO): void;

    public function close(string $leavePeriodId, int $closingBalance, \DateTimeImmutable $closedAt): void;
}
