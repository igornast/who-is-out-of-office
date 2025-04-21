<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\UserRepositoryInterface;

class GetUsersWithBirthdaysForDates
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function handle(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->userRepository->findUsersWithIncomingBirthdays(start:  $startDate, end: $endDate);
    }
}
