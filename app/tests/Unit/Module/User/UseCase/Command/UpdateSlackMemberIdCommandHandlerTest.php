<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\UpdateSlackMemberIdCommandHandler;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);

    $this->handler = new UpdateSlackMemberIdCommandHandler(
        userRepository: $this->userRepository,
    );
});

it('delegates slack member id update to the repository', function () {
    $this->userRepository
        ->expects('updateSlackMemberId')
        ->once()
        ->with('user-1', 'U12345ABC');

    $this->handler->handle('user-1', 'U12345ABC');
});
