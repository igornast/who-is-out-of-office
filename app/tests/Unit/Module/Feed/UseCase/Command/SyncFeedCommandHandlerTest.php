<?php

declare(strict_types=1);

use App\Module\Feed\FeedClientInterface;
use App\Module\Feed\Repository\FeedItemRepositoryInterface;
use App\Module\Feed\UseCase\Command\SyncFeedCommandHandler;
use App\Tests\_fixtures\Shared\DTO\Feed\FeedItemDTOFixture;
use Psr\Log\LoggerInterface;

beforeEach(function (): void {
    $this->client = mock(FeedClientInterface::class);
    $this->repository = mock(FeedItemRepositoryInterface::class);
    $this->logger = mock(LoggerInterface::class);
    $this->handler = new SyncFeedCommandHandler($this->client, $this->repository, $this->logger);
});

it('fetches and upserts items, logs start and completion', function (): void {
    $items = [FeedItemDTOFixture::create(), FeedItemDTOFixture::create()];

    $this->logger->expects('info')->twice();
    $this->client->expects('fetch')->once()->andReturn($items);
    $this->repository->expects('upsertMany')->once()->with($items);

    $this->handler->handle();
});

it('calls upsertMany with empty array when fetch returns empty', function (): void {
    $this->logger->expects('info')->twice();
    $this->client->expects('fetch')->once()->andReturn([]);
    $this->repository->expects('upsertMany')->once()->with([]);

    $this->handler->handle();
});
