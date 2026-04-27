<?php

declare(strict_types=1);

namespace App\Module\Feed\Repository;

use App\Shared\DTO\Feed\FeedItemDTO;

interface FeedItemRepositoryInterface
{
    /**
     * @param FeedItemDTO[] $items
     */
    public function upsertMany(array $items): void;

    /**
     * @return FeedItemDTO[]
     */
    public function findRecent(int $limit): array;

    public function countNewerThan(\DateTimeImmutable $reference): int;
}
