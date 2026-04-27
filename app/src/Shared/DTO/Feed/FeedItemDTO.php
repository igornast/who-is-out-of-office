<?php

declare(strict_types=1);

namespace App\Shared\DTO\Feed;

use App\Shared\Enum\FeedItemTypeEnum;
use Ramsey\Uuid\Uuid;

readonly class FeedItemDTO
{
    public function __construct(
        public string $id,
        public string $externalId,
        public string $title,
        public string $url,
        public FeedItemTypeEnum $contentType,
        public \DateTimeImmutable $publishedAt,
        public ?string $summary = null,
    ) {
    }

    /**
     * @param array<string, mixed> $item
     */
    public static function fromFeedJsonItem(array $item): self
    {
        return new self(
            id: Uuid::uuid4()->toString(),
            externalId: (string) $item['id'],
            title: (string) $item['title'],
            url: (string) $item['url'],
            contentType: FeedItemTypeEnum::fromStringOrBlog($item['_content_type'] ?? null),
            publishedAt: new \DateTimeImmutable((string) $item['date_published']),
            summary: isset($item['summary']) ? (string) $item['summary'] : null,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) $row['id'],
            externalId: (string) $row['external_id'],
            title: (string) $row['title'],
            url: (string) $row['url'],
            contentType: FeedItemTypeEnum::fromStringOrBlog((string) $row['content_type']),
            publishedAt: new \DateTimeImmutable((string) $row['published_at']),
            summary: isset($row['summary']) ? (string) $row['summary'] : null,
        );
    }
}
