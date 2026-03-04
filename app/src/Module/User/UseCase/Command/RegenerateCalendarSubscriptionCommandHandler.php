<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;

class RegenerateCalendarSubscriptionCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function handle(string $userId): void
    {
        $salt = bin2hex(random_bytes(16));
        $this->userRepository->updateIcalHashSalt($userId, $salt);
    }
}
