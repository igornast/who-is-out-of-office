<?php

declare(strict_types=1);

use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\DisableTwoFactorCommandHandler;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->handler = new DisableTwoFactorCommandHandler($this->userRepository);
});

it('delegates to repository to clear 2FA data', function (): void {
    $this->userRepository
        ->expects('disableTwoFactor')
        ->once()
        ->with('user-1');

    $this->handler->handle('user-1');
});
