<?php

declare(strict_types=1);

namespace App\Module\Feed\UseCase\Query;

use App\Module\Feed\Repository\FeedItemRepositoryInterface;
use App\Shared\Facade\UserFacadeInterface;

class GetUnreadCountForUserQueryHandler
{
    public function __construct(
        private readonly FeedItemRepositoryInterface $feedItemRepository,
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    public function handle(string $userId): int
    {
        $user = $this->userFacade->getUser($userId);
        if (null === $user) {
            return 0;
        }

        $reference = $user->feedLastSeenAt ?? $user->createdAt;

        return $this->feedItemRepository->countNewerThan($reference);
    }
}
