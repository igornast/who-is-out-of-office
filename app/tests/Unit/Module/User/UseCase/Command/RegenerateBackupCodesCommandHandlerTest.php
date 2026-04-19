<?php

declare(strict_types=1);

use App\Infrastructure\Security\BackupCodeHasher;
use App\Infrastructure\Security\RecoveryCodeGenerator;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\RegenerateBackupCodesCommandHandler;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->codeGenerator = mock(RecoveryCodeGenerator::class);
    $this->backupCodeHasher = mock(BackupCodeHasher::class);
    $this->handler = new RegenerateBackupCodesCommandHandler(
        $this->userRepository,
        $this->codeGenerator,
        $this->backupCodeHasher,
    );
});

it('generates new codes, hashes them, and persists via repository', function (): void {
    $plainCodes = ['ab23-cd45', 'ef67-gh89', 'jk23-mn45', 'pq67-rs89', 'tu23-vw45', 'xy67-ab89', 'cd23-ef45', 'gh67-jk89'];

    $this->codeGenerator
        ->expects('generate')
        ->once()
        ->andReturn($plainCodes);

    $this->backupCodeHasher
        ->expects('hash')
        ->times(8)
        ->andReturn('hashed-code');

    $this->userRepository
        ->expects('updateBackupCodes')
        ->once()
        ->withArgs(fn (string $userId, array $hashedCodes): bool => 'user-1' === $userId
                && 8 === count($hashedCodes));

    $result = $this->handler->handle('user-1');

    expect($result)->toBe($plainCodes);
});
