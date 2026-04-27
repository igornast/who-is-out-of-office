<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;

class UpdateUserFeedLastSeenAtCommandHandler
{
    public function __construct(private readonly UserRepositoryInterface $userRepository)
    {
    }

    public function handle(string $userId, \DateTimeImmutable $seenAt): void
    {
        $this->userRepository->updateFeedLastSeenAt($userId, $seenAt);
    }
}
