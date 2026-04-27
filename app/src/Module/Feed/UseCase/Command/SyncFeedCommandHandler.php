<?php

declare(strict_types=1);

namespace App\Module\Feed\UseCase\Command;

use App\Module\Feed\FeedClientInterface;
use App\Module\Feed\Repository\FeedItemRepositoryInterface;
use Psr\Log\LoggerInterface;

class SyncFeedCommandHandler
{
    public function __construct(
        private readonly FeedClientInterface $feedClient,
        private readonly FeedItemRepositoryInterface $feedItemRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(): void
    {
        $this->logger->info('[FEED][SYNC]: Feed sync started.');

        $items = $this->feedClient->fetch();
        $this->feedItemRepository->upsertMany($items);

        $items
            |> count(...)
            |> (fn ($x) => sprintf('[FEED][SYNC]: Feed sync completed. Upserted %d items.', $x))
            |> $this->logger->info(...);
    }
}
