<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\Repository;

use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;

interface LeaveRequestTypeRepositoryInterface
{
    public function findById(string $id): ?LeaveRequestTypeDTO;

    /**
     * @return LeaveRequestTypeDTO[]
     */
    public function findAllActive(): array;
}
