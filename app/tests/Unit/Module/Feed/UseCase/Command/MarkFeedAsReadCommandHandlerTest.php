<?php

declare(strict_types=1);

use App\Module\Feed\UseCase\Command\MarkFeedAsReadCommandHandler;
use App\Shared\Facade\UserFacadeInterface;

it('updates feedLastSeenAt for the user', function (): void {
    $userFacade = mock(UserFacadeInterface::class);
    $handler = new MarkFeedAsReadCommandHandler($userFacade);

    $userFacade->expects('updateFeedLastSeenAt')
        ->once()
        ->withArgs(fn (string $userId, DateTimeImmutable $seenAt): bool => 'user-id' === $userId
                && abs(time() - $seenAt->getTimestamp()) < 5);

    $handler->handle('user-id');
});
