<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum FeedItemTypeEnum: string
{
    case Blog = 'blog';
    case Changelog = 'changelog';
    case Announcement = 'announcement';

    public static function fromStringOrBlog(?string $value): self
    {
        if (null === $value) {
            return self::Blog;
        }

        return self::tryFrom($value) ?? self::Blog;
    }
}
