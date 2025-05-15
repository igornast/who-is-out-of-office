<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Service\RoleTranslator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Ramsey\Uuid\Uuid;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AdminCrud(routePath: '/my-team', routeName: 'app_users')]
class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RoleTranslator $roleTranslator,
        private readonly UserPasswordHasherInterface $passwordHasher,
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
        yield FormField::addColumn(3);

        yield ImageField::new('profileImageUrl')
            ->setBasePath('uploads/profile_images')
            ->hideOnForm();

        yield ImageField::new('profileImageUrl')
            ->setBasePath('uploads/profile_images')
            ->setUploadDir('public/uploads/profile_images')
            ->setUploadedFileNamePattern('[slug]-[uuid].[extension]')
            ->onlyOnForms();

        yield FormField::addColumn(9);

        yield TextField::new('firstName');
        yield TextField::new('lastName');
        yield TextField::new('email');
        yield ArrayField::new('roles')
            ->hideOnDetail()
            ->formatValue(fn (array $roles, User $user): string => $this->roleTranslator->translate($roles));

        yield TextField::new('plainPassword')
            ->onlyOnForms()
            ->setRequired(Crud::PAGE_NEW === $pageName);
    }

    public function createEntity(string $entityFqcn): User
    {
        return new User(
            id: Uuid::uuid4(),
            firstName: '',
            lastName: '',
            email: '',
            password: '',
        );
    }

    /**
     * @param User $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $entityInstance->password = $this->passwordHasher->hashPassword($entityInstance, $entityInstance->plainPassword);

        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * @param User $entityInstance
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (isset($entityInstance->plainPassword)) {
            $entityInstance->password = $this->passwordHasher->hashPassword($entityInstance, $entityInstance->plainPassword);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
