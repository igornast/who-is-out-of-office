<?php

declare(strict_types=1);

namespace App\Module\Feed\UseCase\Query;

use App\Module\Feed\Repository\FeedItemRepositoryInterface;
use App\Shared\DTO\Feed\FeedItemDTO;
use App\Shared\Enum\FeedItemTypeEnum;

class GetRecentFeedItemsQueryHandler
{
    public function __construct(
        private readonly FeedItemRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array{blog: FeedItemDTO[], changelog: FeedItemDTO[], announcement: FeedItemDTO[]}
     */
    public function handleGrouped(int $limit): array
    {
        $grouped = [
            FeedItemTypeEnum::Blog->value => [],
            FeedItemTypeEnum::Changelog->value => [],
            FeedItemTypeEnum::Announcement->value => [],
        ];

        foreach ($this->repository->findRecent($limit) as $item) {
            $grouped[$item->contentType->value][] = $item;
        }

        return $grouped;
    }
}
