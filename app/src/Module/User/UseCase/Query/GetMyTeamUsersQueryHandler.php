<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\UserDTO;

class GetMyTeamUsersQueryHandler
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    /**
     * @return UserDTO[]
     */
    public function handle(string $userId): array
    {
        return collect($this->userRepository->findAll())
            ->filter(fn (UserDTO $userDTO) => $userDTO->id !== $userId)
            ->toArray();
    }
}
