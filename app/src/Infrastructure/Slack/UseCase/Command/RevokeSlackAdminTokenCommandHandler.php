<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\Repository\SlackAdminTokenRepositoryInterface;

class RevokeSlackAdminTokenCommandHandler
{
    public function __construct(
        private readonly SlackAdminTokenRepositoryInterface $repository,
    ) {
    }

    public function handle(): void
    {
        $this->repository->deleteAll();
    }
}
