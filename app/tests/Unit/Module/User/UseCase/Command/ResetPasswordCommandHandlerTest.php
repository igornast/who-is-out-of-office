<?php

declare(strict_types=1);

use App\Module\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\ResetPasswordCommandHandler;
use App\Tests\_fixtures\Shared\DTO\PasswordResetTokenDTOFixture;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

beforeEach(function (): void {
    $this->tokenRepository = mock(PasswordResetTokenRepositoryInterface::class);
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->passwordHasher = mock(UserPasswordHasherInterface::class);

    $this->handler = new ResetPasswordCommandHandler(
        $this->tokenRepository,
        $this->userRepository,
        $this->passwordHasher,
    );
});

describe('ResetPasswordCommandHandler', function (): void {
    it('returns true and updates password for valid token', function (): void {
        $tokenDTO = PasswordResetTokenDTOFixture::create();

        $this->tokenRepository->expects('findOneByToken')->with($tokenDTO->token)->andReturn($tokenDTO);
        $this->passwordHasher->expects('hashPassword')->andReturn('hashed-password');
        $this->userRepository->expects('updatePassword')->with($tokenDTO->user->id, 'hashed-password')->once();
        $this->tokenRepository->expects('removeByToken')->with($tokenDTO->token)->once();

        $result = $this->handler->handle($tokenDTO->token, 'new-password');

        expect($result)->toBeTrue();
    });

    it('returns false for missing token', function (): void {
        $this->tokenRepository->expects('findOneByToken')->with('non-existent')->andReturn(null);

        $result = $this->handler->handle('non-existent', 'new-password');

        expect($result)->toBeFalse();
    });

    it('returns false and removes expired token', function (): void {
        $tokenDTO = PasswordResetTokenDTOFixture::create([
            'expiresAt' => new DateTimeImmutable('-1 hour'),
        ]);

        $this->tokenRepository->expects('findOneByToken')->with($tokenDTO->token)->andReturn($tokenDTO);
        $this->tokenRepository->expects('removeByToken')->with($tokenDTO->token)->once();

        $result = $this->handler->handle($tokenDTO->token, 'new-password');

        expect($result)->toBeFalse();
    });

    it('deletes token after successful password reset', function (): void {
        $tokenDTO = PasswordResetTokenDTOFixture::create();

        $this->tokenRepository->expects('findOneByToken')->andReturn($tokenDTO);
        $this->passwordHasher->expects('hashPassword')->andReturn('hashed');
        $this->userRepository->expects('updatePassword');
        $this->tokenRepository->expects('removeByToken')->with($tokenDTO->token)->once();

        $this->handler->handle($tokenDTO->token, 'new-password');
    });
});
