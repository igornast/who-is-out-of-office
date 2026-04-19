<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

class BackupCodeHasher
{
    private readonly NativePasswordHasher $hasher;

    public function __construct()
    {
        $this->hasher = new NativePasswordHasher(opsLimit: 4, memLimit: 9_437_184, algorithm: PASSWORD_ARGON2ID);
    }

    public function hash(string $code): string
    {
        return $this->hasher->hash($code);
    }

    public function verify(string $hashedCode, string $plainCode): bool
    {
        return $this->hasher->verify($hashedCode, $plainCode);
    }
}
