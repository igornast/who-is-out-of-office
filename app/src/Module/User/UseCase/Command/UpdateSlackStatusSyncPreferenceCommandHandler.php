<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;

class UpdateSlackStatusSyncPreferenceCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function handle(string $userId, bool $enabled): bool
    {
        return $this->userRepository->updateSlackStatusSyncEnabled($userId, $enabled);
    }
}
