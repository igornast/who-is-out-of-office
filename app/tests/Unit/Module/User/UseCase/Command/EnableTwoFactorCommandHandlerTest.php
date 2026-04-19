<?php

declare(strict_types=1);

use App\Infrastructure\Security\BackupCodeHasher;
use App\Infrastructure\Security\RecoveryCodeGenerator;
use App\Infrastructure\Security\TotpSecretEncryptor;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Module\User\UseCase\Command\EnableTwoFactorCommandHandler;

beforeEach(function (): void {
    $this->userRepository = mock(UserRepositoryInterface::class);
    $this->encryptor = mock(TotpSecretEncryptor::class);
    $this->codeGenerator = mock(RecoveryCodeGenerator::class);
    $this->backupCodeHasher = mock(BackupCodeHasher::class);
    $this->handler = new EnableTwoFactorCommandHandler(
        $this->userRepository,
        $this->encryptor,
        $this->codeGenerator,
        $this->backupCodeHasher,
    );
});

it('encrypts the secret, hashes recovery codes, and persists via repository', function (): void {
    $this->encryptor
        ->expects('encrypt')
        ->once()
        ->with('JBSWY3DPEHPK3PXP')
        ->andReturn('encrypted-secret-data');

    $this->codeGenerator
        ->expects('generate')
        ->once()
        ->andReturn(['ab23-cd45', 'ef67-gh89', 'jk23-mn45', 'pq67-rs89', 'tu23-vw45', 'xy67-ab89', 'cd23-ef45', 'gh67-jk89']);

    $this->backupCodeHasher
        ->expects('hash')
        ->times(8)
        ->andReturn('hashed-code');

    $this->userRepository
        ->expects('enableTwoFactor')
        ->once()
        ->withArgs(fn (string $userId, string $encryptedSecret, array $hashedCodes): bool => 'user-1' === $userId
                && 'encrypted-secret-data' === $encryptedSecret
                && 8 === count($hashedCodes));

    $result = $this->handler->handle('user-1', 'JBSWY3DPEHPK3PXP');

    expect($result)->toHaveCount(8)
        ->and($result[0])->toBe('ab23-cd45');
});
