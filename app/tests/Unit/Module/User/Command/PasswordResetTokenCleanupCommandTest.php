<?php

declare(strict_types=1);

use App\Module\User\Command\PasswordResetTokenCleanupCommand;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Component\Console\Command\Command;

beforeEach(function (): void {
    $this->userFacade = mock(UserFacadeInterface::class);

    $this->command = new PasswordResetTokenCleanupCommand(userFacade: $this->userFacade);
});

it('cleans up expired password reset tokens and returns success', function () {
    $this->userFacade
        ->expects('cleanupExpiredPasswordResetTokens')
        ->once();

    $result = ($this->command)();

    expect($result)->toBe(Command::SUCCESS);
});
