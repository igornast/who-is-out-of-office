<?php

declare(strict_types=1);

namespace App\Shared\DTO;

use App\Infrastructure\Doctrine\Entity\PasswordResetToken;

class PasswordResetTokenDTO
{
    public function __construct(
        public string $id,
        public string $token,
        public UserDTO $user,
        public \DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromEntity(PasswordResetToken $entity): self
    {
        return new self(
            id: $entity->id->toString(),
            token: $entity->token,
            user: UserDTO::fromEntity($entity->user),
            expiresAt: $entity->expiresAt,
            createdAt: $entity->createdAt,
        );
    }
}
