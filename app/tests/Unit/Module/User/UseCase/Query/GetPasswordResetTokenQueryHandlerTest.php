<?php

declare(strict_types=1);

use App\Module\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Module\User\UseCase\Query\GetPasswordResetTokenQueryHandler;
use App\Tests\_fixtures\Shared\DTO\PasswordResetTokenDTOFixture;

beforeEach(function (): void {
    $this->tokenRepository = mock(PasswordResetTokenRepositoryInterface::class);
    $this->handler = new GetPasswordResetTokenQueryHandler($this->tokenRepository);
});

describe('GetPasswordResetTokenQueryHandler', function (): void {
    it('returns token DTO when found', function (): void {
        $tokenDTO = PasswordResetTokenDTOFixture::create();

        $this->tokenRepository->expects('findOneByToken')->with('valid-token')->andReturn($tokenDTO)->once();

        $result = $this->handler->handle('valid-token');

        expect($result)->toBe($tokenDTO);
    });

    it('returns null when token not found', function (): void {
        $this->tokenRepository->expects('findOneByToken')->with('invalid-token')->andReturn(null)->once();

        $result = $this->handler->handle('invalid-token');

        expect($result)->toBeNull();
    });
});
