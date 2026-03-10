<?php

declare(strict_types=1);

use App\Module\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Module\User\UseCase\Command\CleanupExpiredPasswordResetTokensCommandHandler;

beforeEach(function (): void {
    $this->tokenRepository = mock(PasswordResetTokenRepositoryInterface::class);
    $this->handler = new CleanupExpiredPasswordResetTokensCommandHandler($this->tokenRepository);
});

describe('CleanupExpiredPasswordResetTokensCommandHandler', function (): void {
    it('delegates to repository and returns removed count', function (): void {
        $this->tokenRepository->expects('removeExpired')->andReturn(5);

        $result = $this->handler->handle();

        expect($result)->toBe(5);
    });

    it('returns zero when no tokens expired', function (): void {
        $this->tokenRepository->expects('removeExpired')->andReturn(0);

        $result = $this->handler->handle();

        expect($result)->toBe(0);
    });
});
