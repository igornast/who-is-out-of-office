<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Query;

use App\Infrastructure\Slack\Repository\SlackAdminTokenRepositoryInterface;
use App\Shared\DTO\Slack\SlackAdminTokenDTO;

class GetSlackAdminTokenQueryHandler
{
    public function __construct(
        private readonly SlackAdminTokenRepositoryInterface $repository,
    ) {
    }

    public function handle(): ?SlackAdminTokenDTO
    {
        return $this->repository->findCurrent();
    }
}
