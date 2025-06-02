<?php

declare(strict_types=1);

namespace App\Shared\DTO;

use App\Infrastructure\Doctrine\Entity\Invitation;

class InvitationDTO
{
    public function __construct(
        public string $id,
        public string $token,
        public UserDTO $user,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public static function fromEntity(Invitation $invitation): self
    {
        return new self(
            id: $invitation->id->toString(),
            token: $invitation->token,
            user: UserDTO::fromEntity($invitation->user),
            createdAt: $invitation->createdAt,
        );
    }
}
