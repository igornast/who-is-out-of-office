<?php

declare(strict_types=1);

use App\Infrastructure\Slack\Repository\SlackAdminTokenRepositoryInterface;
use App\Infrastructure\Slack\UseCase\Command\RevokeSlackAdminTokenCommandHandler;

it('deletes the stored token', function (): void {
    $repository = mock(SlackAdminTokenRepositoryInterface::class);
    $repository->expects('deleteAll')->once();

    $handler = new RevokeSlackAdminTokenCommandHandler($repository);
    $handler->handle();
});
