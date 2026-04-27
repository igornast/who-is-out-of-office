<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\UpdateUserFeedLastSeenAtCommandHandler;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->handler = new UpdateUserFeedLastSeenAtCommandHandler($this->userRepository);
});

it('delegates updateFeedLastSeenAt to the repository', function (): void {
    $seenAt = new DateTimeImmutable('2026-05-01 12:00:00');

    $this->userRepository
        ->expects('updateFeedLastSeenAt')
        ->once()
        ->with('user-id', $seenAt);

    $this->handler->handle('user-id', $seenAt);
});
