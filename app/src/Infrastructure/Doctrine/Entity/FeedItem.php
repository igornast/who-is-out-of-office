<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use App\Shared\Enum\FeedItemTypeEnum;
use Ramsey\Uuid\UuidInterface;

class FeedItem
{
    use TimestampableTrait;

    public function __construct(
        public UuidInterface $id,
        public string $externalId,
        public string $title,
        public string $url,
        public FeedItemTypeEnum $contentType,
        public \DateTimeImmutable $publishedAt,
        public \DateTimeImmutable $fetchedAt,
        public ?string $summary = null,
    ) {
        $this->initializeTimestamps();
    }
}
