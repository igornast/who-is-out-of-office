<?php

declare(strict_types=1);

use App\Shared\DTO\Feed\FeedItemDTO;
use App\Shared\Enum\FeedItemTypeEnum;

it('builds from feed JSON item with all fields', function (): void {
    $dto = FeedItemDTO::fromFeedJsonItem([
        'id' => 'blog-2026-04-15-burnout',
        'url' => 'https://whoisooo.app/blog/burnout',
        'title' => 'Managing burnout',
        'summary' => 'Five patterns…',
        '_content_type' => 'blog',
        'date_published' => '2026-04-15T10:00:00Z',
    ]);

    expect($dto->externalId)->toBe('blog-2026-04-15-burnout')
        ->and($dto->url)->toBe('https://whoisooo.app/blog/burnout')
        ->and($dto->title)->toBe('Managing burnout')
        ->and($dto->summary)->toBe('Five patterns…')
        ->and($dto->contentType)->toBe(FeedItemTypeEnum::Blog)
        ->and($dto->publishedAt->format(DATE_ATOM))->toBe('2026-04-15T10:00:00+00:00');
});

it('defaults to Blog when _content_type is absent', function (): void {
    $dto = FeedItemDTO::fromFeedJsonItem([
        'id' => 'x',
        'url' => 'https://example.com',
        'title' => 't',
        'date_published' => '2026-04-15T10:00:00Z',
    ]);

    expect($dto->contentType)->toBe(FeedItemTypeEnum::Blog)
        ->and($dto->summary)->toBeNull();
});

it('builds from a database row (snake_case)', function (): void {
    $dto = FeedItemDTO::fromArray([
        'id' => '1cb8d9ce-7e36-4f57-b0dd-5e1b6c0b9b00',
        'external_id' => 'blog-1',
        'url' => 'https://x',
        'title' => 't',
        'summary' => null,
        'content_type' => 'changelog',
        'published_at' => '2026-04-15 10:00:00',
    ]);

    expect($dto->id)->toBe('1cb8d9ce-7e36-4f57-b0dd-5e1b6c0b9b00')
        ->and($dto->contentType)->toBe(FeedItemTypeEnum::Changelog);
});
