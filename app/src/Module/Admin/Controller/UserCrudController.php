<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Constants\UserSettings;
use App\Shared\Enum\RoleEnum;
use App\Shared\Service\RoleTranslator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Ramsey\Uuid\Uuid;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @extends AppAbstractCrudController<User>
 */
#[AdminRoute(path: '/my-organization', name: 'app_users')]
class UserCrudController extends AppAbstractCrudController
{
    public function __construct(
        private readonly RoleTranslator $roleTranslator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud->setSearchFields(['firstName', 'lastName', 'email']);

        return $crud->setPageTitle(Action::INDEX, 'crud.title.user');
    }

    public function configureFilters(Filters $filters): Filters
    {
        if (!$this->isAdmin()) {
            return $filters;
        }

        return $filters
            ->add('isActive')
            ->add(ChoiceFilter::new('roles')->setChoices(RoleEnum::getChoices()));
    }

    public function configureActions(Actions $actions): Actions
    {
        $resetPasswordAction = Action::new('resetPassword', 'crud.user.action.reset_password')
            ->linkToUrl(
                fn (User $entity) => $this->urlGenerator->generate('app_user_reset_password', [
                    'id' => $entity->id,
                    '_token' => $this->csrfTokenManager->getToken(sprintf('resetPassword%s', $entity->id))->getValue(),
                ])
            )
            ->setHtmlAttributes(['data-lr-action' => 'resetPassword'])
            ->setIcon('icon-key-round')
            ->addCssClass('btn btn-outline')
            ->displayIf(fn (User $entity) => $this->isAdmin() && $entity->id->toString() !== $this->getUser()->id->toString());

        return $actions
            ->setPermission(Action::NEW, RoleEnum::Admin->value)
            ->setPermission(Action::DELETE, RoleEnum::Admin->value)
            ->setPermission(Action::EDIT, new Expression(sprintf('"%s" in role_names or "%s" in role_names', RoleEnum::Admin->value, RoleEnum::Manager->value)))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $resetPasswordAction)
            ->add(Crud::PAGE_EDIT, $resetPasswordAction);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Profile');

        yield FormField::addColumn(3);
        yield ImageField::new('profileImageUrl')
            ->setBasePath('uploads/profile_images')
            ->hideOnForm();
        yield ImageField::new('profileImageUrl')
            ->setBasePath('uploads/profile_images')
            ->setUploadDir('public/uploads/profile_images')
            ->setUploadedFileNamePattern('[slug]-[uuid].[extension]')
            ->onlyWhenUpdating();

        yield FormField::addColumn(9);
        yield TextField::new('email');
        yield TextField::new('firstName')->hideWhenCreating();
        yield TextField::new('lastName')->hideWhenCreating();
        yield DateField::new('birthDate')->hideWhenCreating();
        yield BooleanField::new('isActive', 'Status')
            ->renderAsSwitch(false)
            ->onlyOnIndex();

        if ($this->isAdminOrManager()) {
            yield FormField::addTab('Employee of record');

            yield FormField::addColumn(7);
            yield FormField::addFieldset('Absence Balance');
            yield NumberField::new('annualLeaveAllowance')->hideOnIndex();
            yield NumberField::new('currentLeaveBalance')
                ->setHelp('The number of available holiday days for the employee.')
                ->hideOnIndex();
            yield DateField::new('absenceBalanceResetDay')
                ->setHelp('The next date when the absence balance will be reset annually.')
                ->hideOnIndex();

            yield AssociationField::new('manager')
                ->setHelp('The direct manager of this employee.')
                ->hideOnIndex();

            yield FormField::addFieldset('Contract Details');
            yield DateField::new('contractStartedAt')
                ->setColumns(6)
                ->hideOnIndex();
            yield BooleanField::new('hasCelebrateWorkAnniversary')
                ->setHelp('Have the employee enabled work anniversaries celebrations.')
                ->setColumns(6)
                ->hideOnIndex()
                ->setDisabled();

            yield FormField::addColumn(5);
            yield ChoiceField::new('workingDays')
                ->hideOnIndex()
                ->setChoices(UserSettings::WORKING_DAYS)
                ->allowMultipleChoices()
                ->renderExpanded();

            yield FormField::addTab('Security')->hideOnDetail();
            yield ArrayField::new('roles')
                ->hideOnDetail()
                ->formatValue(fn (array $roles, User $user): string => $this->roleTranslator->translate($roles));

            yield TextField::new('plainPassword')
                ->onlyWhenUpdating()->setRequired(false);
        }
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
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (isset($entityInstance->plainPassword)) {
            $entityInstance->password = $this->passwordHasher->hashPassword($entityInstance, $entityInstance->plainPassword);
        }

        $existingUser = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        if (empty($entityInstance->profileImageUrl) && !empty($existingUser['profileImageUrl'])) {
            $entityInstance->profileImageUrl = $existingUser['profileImageUrl'];
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
