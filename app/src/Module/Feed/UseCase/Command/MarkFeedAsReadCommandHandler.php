<?php

declare(strict_types=1);

namespace App\Module\Feed\UseCase\Command;

use App\Shared\Facade\UserFacadeInterface;

class MarkFeedAsReadCommandHandler
{
    public function __construct(private readonly UserFacadeInterface $userFacade)
    {
    }

    public function handle(string $userId): void
    {
        $this->userFacade->updateFeedLastSeenAt($userId, new \DateTimeImmutable());
    }
}
