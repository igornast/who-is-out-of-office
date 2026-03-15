<?php

declare(strict_types=1);

use App\Module\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\CreatePasswordResetTokenCommandHandler;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Symfony\Component\Clock\MockClock;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->tokenRepository = mock(PasswordResetTokenRepositoryInterface::class);
    $this->clock = new MockClock(new DateTimeImmutable('2026-03-10 12:00:00'));

    $this->handler = new CreatePasswordResetTokenCommandHandler(
        $this->userRepository,
        $this->tokenRepository,
        $this->clock,
    );
});

describe('CreatePasswordResetTokenCommandHandler', function (): void {
    it('returns token for active user', function (): void {
        $userDTO = UserDTOFixture::create(['email' => 'test@ooo.com', 'isActive' => true]);

        $this->userRepository->expects('findOneByEmail')->with('test@ooo.com')->andReturn($userDTO);
        $this->tokenRepository->expects('removeByUserId')->with($userDTO->id)->once();
        $this->tokenRepository->expects('save')->once();

        $result = $this->handler->handle('test@ooo.com');

        expect($result)->toBeString()
            ->and(strlen($result))->toBe(64);
    });

    it('returns null for missing user', function (): void {
        $this->userRepository->expects('findOneByEmail')->with('missing@ooo.com')->andReturn(null);

        $result = $this->handler->handle('missing@ooo.com');

        expect($result)->toBeNull();
    });

    it('returns null for inactive user', function (): void {
        $userDTO = UserDTOFixture::create(['email' => 'inactive@ooo.com', 'isActive' => false]);

        $this->userRepository->expects('findOneByEmail')->with('inactive@ooo.com')->andReturn($userDTO);

        $result = $this->handler->handle('inactive@ooo.com');

        expect($result)->toBeNull();
    });

    it('removes existing tokens before creating a new one', function (): void {
        $userDTO = UserDTOFixture::create(['email' => 'test@ooo.com', 'isActive' => true]);

        $this->userRepository->expects('findOneByEmail')->andReturn($userDTO);
        $this->tokenRepository->expects('removeByUserId')->with($userDTO->id)->once();
        $this->tokenRepository->expects('save')->once();

        $this->handler->handle('test@ooo.com');
    });

    it('saves token with 1-hour expiry', function (): void {
        $userDTO = UserDTOFixture::create(['email' => 'test@ooo.com', 'isActive' => true]);
        $expectedExpiry = new DateTimeImmutable('2026-03-10 13:00:00');

        $this->userRepository->expects('findOneByEmail')->andReturn($userDTO);
        $this->tokenRepository->expects('removeByUserId');
        $this->tokenRepository->expects('save')
            ->withArgs(fn (string $token, string $userId, DateTimeImmutable $expiresAt): bool => 64 === strlen($token)
                    && $userId === $userDTO->id
                    && $expiresAt->getTimestamp() === $expectedExpiry->getTimestamp())
            ->once();

        $this->handler->handle('test@ooo.com');
    });
});
