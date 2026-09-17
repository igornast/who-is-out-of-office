<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Query;

use App\Module\User\Repository\InvitationRepositoryInterface;

class HasPendingInvitationQueryHandler
{
    public function __construct(
        private readonly InvitationRepositoryInterface $invitationRepository,
    ) {
    }

    public function handle(string $userId): bool
    {
        return null !== $this->invitationRepository->findOneByUserId($userId);
    }
}
