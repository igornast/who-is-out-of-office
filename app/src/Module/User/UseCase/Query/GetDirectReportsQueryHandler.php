<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\UserDTO;

class GetDirectReportsQueryHandler
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    /**
     * @return UserDTO[]
     */
    public function handle(string $managerId): array
    {
        return $this->userRepository->findByManagerId($managerId);
    }
}
