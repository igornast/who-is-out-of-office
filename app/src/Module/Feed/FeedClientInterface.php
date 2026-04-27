<?php

declare(strict_types=1);

namespace App\Module\Feed;

use App\Shared\DTO\Feed\FeedItemDTO;

interface FeedClientInterface
{
    /**
     * @return FeedItemDTO[]
     */
    public function fetch(): array;
}
