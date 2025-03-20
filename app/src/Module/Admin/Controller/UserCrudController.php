<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;


use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Doctrine\Repository\UserRepository;
use App\Shared\DTO\UserDTO;
use App\Shared\Service\RoleTranslator;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[AdminCrud(routePath: '/my-team', routeName: 'app_users')]
class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly RoleTranslator $roleTranslator)
    {
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
            TextField::new('firstName'),
            TextField::new('lastName'),
            TextField::new('email'),
            ArrayField::new('roles')
                ->formatValue(fn(array $value, User $user): string => $this->roleTranslator->translate($value[0])),
        ];
    }
}