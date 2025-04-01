<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\UserDTO;

class GetUsersWithIncomingBirthdaysHandler
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    /**
     * @return UserDTO[]
     */
    public function handle(?\DateTimeImmutable $end = null): array
    {
        if (null === $end) {
            $end = new \DateTimeImmutable('+ 20 days');
        }

        return $this->userRepository->getUsersWithIncomingBirthdays(
            start:  new \DateTimeImmutable(),
            end: $end,
        );
    }
}
