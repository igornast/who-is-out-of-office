<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\UserRepositoryInterface;

class UpdateSlackMemberIdCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function handle(string $userId, string $slackMemberId): void
    {
        $this->userRepository->updateSlackMemberId($userId, $slackMemberId);
    }
}
