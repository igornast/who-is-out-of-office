<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\RoleEnum;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @template TEntity of object
 *
 * @extends AbstractCrudController<TEntity>
 */
abstract class AppAbstractCrudController extends AbstractCrudController
{
    protected function getUser(): User
    {
        $user = parent::getUser();

        if (!$user instanceof User) {
            throw new AuthenticationException('User not found');
        }

        return $user;
    }

    protected function isAdmin(): bool
    {
        return $this->isGranted(RoleEnum::Admin->value);
    }

    protected function isAdminOrManager(): bool
    {
        return $this->isGranted(RoleEnum::Admin->value) || $this->isGranted(RoleEnum::Manager->value);
    }

    protected function canManageRequest(LeaveRequest $request): bool
    {
        $currentUser = $this->getUser();

        if ($currentUser->id->toString() === $request->user->id->toString()) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if ($this->isGranted(RoleEnum::Manager->value)) {
            return $request->user->manager?->id->toString() === $currentUser->id->toString();
        }

        return false;
    }
}
