<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

abstract class AppAbstractCrudController extends AbstractCrudController
{
    protected function isAdmin(): bool
    {
        return $this->isGranted('ROLE_ADMIN');
    }
}
