<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use Ramsey\Uuid\UuidInterface;

class Invitation
{
    public function __construct(
        public UuidInterface $id,
        public string $token,
        public User $user,
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
    }
}
