<?php

declare(strict_types=1);

namespace App\Module\User\Repository;

use App\Shared\DTO\PasswordResetTokenDTO;

interface PasswordResetTokenRepositoryInterface
{
    public function findOneByToken(string $token): ?PasswordResetTokenDTO;

    public function save(string $token, string $userId, \DateTimeImmutable $expiresAt): void;

    public function removeByUserId(string $userId): void;

    public function removeByToken(string $token): void;

    public function removeExpired(): int;
}
