<?php

declare(strict_types=1);

use App\Module\Feed\FeedFacade;
use App\Module\Feed\UseCase\Command\MarkFeedAsReadCommandHandler;
use App\Module\Feed\UseCase\Command\SyncFeedCommandHandler;
use App\Module\Feed\UseCase\Query\GetRecentFeedItemsQueryHandler;
use App\Module\Feed\UseCase\Query\GetUnreadCountForUserQueryHandler;

beforeEach(function (): void {
    $this->sync = mock(SyncFeedCommandHandler::class);
    $this->mark = mock(MarkFeedAsReadCommandHandler::class);
    $this->recent = mock(GetRecentFeedItemsQueryHandler::class);
    $this->unread = mock(GetUnreadCountForUserQueryHandler::class);

    $this->facade = new FeedFacade(
        $this->sync,
        $this->mark,
        $this->recent,
        $this->unread,
    );
});

it('delegates sync', function (): void {
    $this->sync->expects('handle')->once();
    $this->facade->sync();
});

it('delegates getRecentItemsGrouped', function (): void {
    $expected = ['blog' => [], 'changelog' => [], 'announcement' => []];
    $this->recent->expects('handleGrouped')->once()->with(50)->andReturn($expected);
    expect($this->facade->getRecentItemsGrouped(50))->toBe($expected);
});

it('delegates getUnreadCountForUser', function (): void {
    $this->unread->expects('handle')->once()->with('uid')->andReturn(4);
    expect($this->facade->getUnreadCountForUser('uid'))->toBe(4);
});

it('delegates markAsReadForUser', function (): void {
    $this->mark->expects('handle')->once()->with('uid');
    $this->facade->markAsReadForUser('uid');
});
