<?php

declare(strict_types=1);

use App\Module\Feed\Repository\FeedItemRepositoryInterface;
use App\Module\Feed\UseCase\Query\GetUnreadCountForUserQueryHandler;
use App\Shared\Facade\UserFacadeInterface;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->feedRepo = mock(FeedItemRepositoryInterface::class);
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->handler = new GetUnreadCountForUserQueryHandler($this->feedRepo, $this->userFacade);
});

it('uses feedLastSeenAt when present', function (): void {
    $seenAt = new DateTimeImmutable('2026-04-20 10:00:00');
    $user = UserDTOFixture::create([
        'id' => 'user-id',
        'createdAt' => new DateTimeImmutable('2025-01-01'),
        'feedLastSeenAt' => $seenAt,
    ]);

    $this->userFacade->expects('getUser')->once()->with('user-id')->andReturn($user);
    $this->feedRepo->expects('countNewerThan')->once()->with($seenAt)->andReturn(3);

    expect($this->handler->handle('user-id'))->toBe(3);
});

it('falls back to createdAt when feedLastSeenAt is null', function (): void {
    $createdAt = new DateTimeImmutable('2025-01-01');
    $user = UserDTOFixture::create([
        'id' => 'user-id',
        'createdAt' => $createdAt,
        'feedLastSeenAt' => null,
    ]);

    $this->userFacade->expects('getUser')->once()->andReturn($user);
    $this->feedRepo->expects('countNewerThan')->once()->with($createdAt)->andReturn(7);

    expect($this->handler->handle('user-id'))->toBe(7);
});

it('returns 0 when user is not found', function (): void {
    $this->userFacade->expects('getUser')->once()->andReturn(null);
    $this->feedRepo->shouldNotReceive('countNewerThan');

    expect($this->handler->handle('missing'))->toBe(0);
});
