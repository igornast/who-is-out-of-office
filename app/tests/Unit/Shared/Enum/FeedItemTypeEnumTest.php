<?php

declare(strict_types=1);

use App\Shared\Enum\FeedItemTypeEnum;

it('exposes blog, changelog, announcement values', function (): void {
    expect(FeedItemTypeEnum::Blog->value)->toBe('blog')
        ->and(FeedItemTypeEnum::Changelog->value)->toBe('changelog')
        ->and(FeedItemTypeEnum::Announcement->value)->toBe('announcement');
});

it('falls back to Blog for unknown raw value', function (): void {
    expect(FeedItemTypeEnum::fromStringOrBlog(null))->toBe(FeedItemTypeEnum::Blog)
        ->and(FeedItemTypeEnum::fromStringOrBlog(''))->toBe(FeedItemTypeEnum::Blog)
        ->and(FeedItemTypeEnum::fromStringOrBlog('mystery'))->toBe(FeedItemTypeEnum::Blog)
        ->and(FeedItemTypeEnum::fromStringOrBlog('changelog'))->toBe(FeedItemTypeEnum::Changelog);
});
