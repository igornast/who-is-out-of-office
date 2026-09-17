<?php

declare(strict_types=1);

namespace App\Module\User\UseCase\Command;

use App\Module\User\Repository\InvitationRepositoryInterface;
use App\Module\User\Repository\UserRepositoryInterface;
use App\Shared\DTO\InvitationDTO;
use App\Shared\DTO\UserDTO;

class IssueUserInvitationCommandHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly InvitationRepositoryInterface $invitationRepository,
    ) {
    }

    public function handle(string $userId): ?InvitationDTO
    {
        $userDTO = $this->userRepository->findOneById($userId);

        if (!$userDTO instanceof UserDTO) {
            return null;
        }

        if ($userDTO->isActive) {
            return null;
        }

        if (null === $this->invitationRepository->findOneByUserId($userId)) {
            return null;
        }

        return $this->invitationRepository->replaceForUser($userId, bin2hex(random_bytes(32)));
    }
}
