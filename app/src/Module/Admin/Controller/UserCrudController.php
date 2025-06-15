<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\Invitation;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Constants\UserSettings;
use App\Shared\Service\RoleTranslator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Ramsey\Uuid\Uuid;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AdminCrud(routePath: '/my-team', routeName: 'app_users')]
class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
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
            ->onlyWhenUpdating();

        yield FormField::addColumn(9);

        yield TextField::new('firstName')->hideWhenCreating();
        yield TextField::new('lastName')->hideWhenCreating();
        yield TextField::new('email');
        yield NumberField::new('annualLeaveAllowance')->onlyWhenCreating();
        yield ChoiceField::new('workingDays')
            ->setChoices(UserSettings::WORKING_DAYS)
            ->allowMultipleChoices()
            ->renderExpanded();

        yield ArrayField::new('roles')
            ->hideOnDetail()
            ->formatValue(fn (array $roles, User $user): string => $this->roleTranslator->translate($roles));

        yield TextField::new('invitationCopy', 'Status')
            ->setVirtual(true)
            ->setValue('')
            ->formatValue(fn ($value, $user) => $this->generateInvitationButton($value, $user))
            ->onlyOnIndex()
            ->renderAsHtml();

        yield TextField::new('plainPassword')
            ->onlyWhenUpdating()
            ->setRequired(Crud::PAGE_EDIT === $pageName);
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

    private function generateInvitationButton(mixed $value, User $user)
    {

        $invitation = $this->em
            ->getRepository(Invitation::class)
            ->findOneBy(['user' => $user]);

        if (!$invitation) {
            return 'Active';
        }

        $invitationUrl = $this->generateUrl(
            route: 'app_user_invitation',
            parameters: ['token' => $invitation->token],
            referenceType: UrlGeneratorInterface::ABSOLUTE_URL
        );

        $buttonId = 'copy-invite-'.$user->id;

        return <<<HTML
            <button 
                id="{$buttonId}" 
                class="btn btn-sm btn-outline-secondary" 
                data-invitation-url="{$invitationUrl}"
                onclick="copyInvitationLink(this)"
            >
                Copy Invitation Link
            </button>
        <script>
            function copyInvitationLink(button) {
                navigator.clipboard.writeText(button.dataset.invitationUrl);
                button.innerText = 'Copied!';
                setTimeout(() => button.innerText = 'Copy Invite', 2000);
            }
        </script>

        HTML;
    }
}
