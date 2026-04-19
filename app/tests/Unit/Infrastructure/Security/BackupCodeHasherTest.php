<?php

declare(strict_types=1);

use App\Infrastructure\Security\BackupCodeHasher;

it('hashes and verifies a backup code', function (): void {
    $hasher = new BackupCodeHasher();
    $hashed = $hasher->hash('ab23-cd45');

    expect($hashed)->not->toBe('ab23-cd45')
        ->and($hasher->verify($hashed, 'ab23-cd45'))->toBeTrue()
        ->and($hasher->verify($hashed, 'wrong-code'))->toBeFalse();
});
