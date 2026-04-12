<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Repository\SlackAdminTokenRepositoryInterface;
use App\Infrastructure\Slack\UseCase\Query\GetSlackAdminTokenQueryHandler;
use App\Tests\_fixtures\Shared\DTO\Slack\SlackAdminTokenDTOFixture;

beforeEach(function (): void {
    $this->repository = mock(SlackAdminTokenRepositoryInterface::class);
    $this->handler = new GetSlackAdminTokenQueryHandler($this->repository);
});

it('returns the current token from the repository', function (): void {
    $dto = SlackAdminTokenDTOFixture::create();
    $this->repository->expects('findCurrent')->once()->andReturn($dto);

    expect($this->handler->handle())->toBe($dto);
});

it('returns null when no token is stored', function (): void {
    $this->repository->expects('findCurrent')->once()->andReturn(null);

    expect($this->handler->handle())->toBeNull();
});
