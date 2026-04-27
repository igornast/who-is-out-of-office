<?php

declare(strict_types=1);

use App\Module\Feed\Repository\FeedItemRepositoryInterface;
use App\Module\Feed\UseCase\Query\GetRecentFeedItemsQueryHandler;
use App\Shared\Enum\FeedItemTypeEnum;
use App\Tests\_fixtures\Shared\DTO\Feed\FeedItemDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(FeedItemRepositoryInterface::class);
    $this->handler = new GetRecentFeedItemsQueryHandler($this->repository);
});

it('groups results by content type preserving order', function (): void {
    $blog = FeedItemDTOFixture::create(['contentType' => FeedItemTypeEnum::Blog]);
    $change = FeedItemDTOFixture::create(['contentType' => FeedItemTypeEnum::Changelog]);
    $announce = FeedItemDTOFixture::create(['contentType' => FeedItemTypeEnum::Announcement]);

    $this->repository->allows('findRecent')->andReturn([$blog, $change, $announce, $blog]);

    $grouped = $this->handler->handleGrouped(50);

    expect($grouped['blog'])->toHaveCount(2)
        ->and($grouped['changelog'])->toHaveCount(1)
        ->and($grouped['announcement'])->toHaveCount(1);
});
