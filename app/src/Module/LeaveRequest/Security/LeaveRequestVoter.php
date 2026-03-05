<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\Security;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestPermission;
use App\Shared\Enum\RoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, LeaveRequestDTO>
 */
class LeaveRequestVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== LeaveRequestPermission::tryFrom($attribute)
            && $subject instanceof LeaveRequestDTO;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match (LeaveRequestPermission::from($attribute)) {
            LeaveRequestPermission::Withdraw => $this->canWithdraw($user, $subject),
            LeaveRequestPermission::Manage => $this->canManage($user, $subject),
        };
    }

    private function canWithdraw(User $user, LeaveRequestDTO $dto): bool
    {
        return $user->id->toString() === $dto->user->id;
    }

    private function canManage(User $user, LeaveRequestDTO $dto): bool
    {
        $roles = $user->getRoles();
        $isAdmin = in_array(RoleEnum::Admin->value, $roles, true);
        $isManager = in_array(RoleEnum::Manager->value, $roles, true);

        if (!$isAdmin && !$isManager) {
            return false;
        }

        $currentUserId = $user->id->toString();

        if ($currentUserId === $dto->user->id) {
            return false;
        }

        if ($isManager && !$isAdmin) {
            return $dto->user->managerId === $currentUserId;
        }

        return true;
    }
}
