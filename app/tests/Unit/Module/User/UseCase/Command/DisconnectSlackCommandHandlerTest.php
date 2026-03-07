<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\DisconnectSlackCommandHandler;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);

    $this->handler = new DisconnectSlackCommandHandler(
        userRepository: $this->userRepository,
    );
});

it('delegates slack integration removal to the repository', function () {
    $this->userRepository
        ->expects('removeSlackIntegration')
        ->once()
        ->with('user-1');

    $this->handler->handle('user-1');
});
