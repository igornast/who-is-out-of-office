<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Shared\DTO\Feed\FeedItemDTO;

interface FeedFacadeInterface
{
    public function sync(): void;

    /**
     * @return array{blog: FeedItemDTO[], changelog: FeedItemDTO[], announcement: FeedItemDTO[]}
     */
    public function getRecentItemsGrouped(int $limit): array;

    public function getUnreadCountForUser(string $userId): int;

    public function markAsReadForUser(string $userId): void;
}
