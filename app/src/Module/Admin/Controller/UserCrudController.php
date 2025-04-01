<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Service\RoleTranslator;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\ExpressionLanguage\Expression;

#[AdminCrud(routePath: '/my-team', routeName: 'app_users')]
class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RoleTranslator $roleTranslator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->setPermission(Action::NEW, 'ROLE_ADMIN')
            ->setPermission(Action::DELETE, 'ROLE_ADMIN')
            ->setPermission(Action::EDIT, new Expression('"ROLE_ADMIN" in role_names or "ROLE_MANAGER" in role_names'));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addColumn(3),
            ImageField::new('profileImageUrl'),

            FormField::addColumn(9),
            TextField::new('firstName'),
            TextField::new('lastName'),
            TextField::new('email'),
            ArrayField::new('roles')
                ->hideOnDetail()
                ->formatValue(function (array $value, User $user): string {
                    /** @var string[] $roles */
                    $roles = $value;

                    return $this->roleTranslator->translate($roles);
                }),
        ];
    }
}
