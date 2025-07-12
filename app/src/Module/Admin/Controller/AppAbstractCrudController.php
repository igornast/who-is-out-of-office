<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
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
        return $this->isGranted('ROLE_ADMIN');
    }
}
