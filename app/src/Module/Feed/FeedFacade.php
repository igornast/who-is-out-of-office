<?php

declare(strict_types=1);

namespace App\Module\Feed;

use App\Module\Feed\UseCase\Command\MarkFeedAsReadCommandHandler;
use App\Module\Feed\UseCase\Command\SyncFeedCommandHandler;
use App\Module\Feed\UseCase\Query\GetRecentFeedItemsQueryHandler;
use App\Module\Feed\UseCase\Query\GetUnreadCountForUserQueryHandler;
use App\Shared\Facade\FeedFacadeInterface;

final class FeedFacade implements FeedFacadeInterface
{
    public function __construct(
        private readonly SyncFeedCommandHandler $syncHandler,
        private readonly MarkFeedAsReadCommandHandler $markHandler,
        private readonly GetRecentFeedItemsQueryHandler $recentHandler,
        private readonly GetUnreadCountForUserQueryHandler $unreadHandler,
    ) {
    }

    public function sync(): void
    {
        $this->syncHandler->handle();
    }

    public function getRecentItemsGrouped(int $limit): array
    {
        return $this->recentHandler->handleGrouped($limit);
    }

    public function getUnreadCountForUser(string $userId): int
    {
        return $this->unreadHandler->handle($userId);
    }

    public function markAsReadForUser(string $userId): void
    {
        $this->markHandler->handle($userId);
    }
}
