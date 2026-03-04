<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Constants\UserSettings;
use App\Shared\Service\RoleTranslator;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AppAbstractCrudController<User>
 */
#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_MANAGER")'))]
#[AdminRoute(path: '/team/members', name: 'app_team_members')]
class TeamMembersCrudController extends AppAbstractCrudController
{
    public function __construct(
        private readonly RoleTranslator $roleTranslator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'crud.title.team_members')
            ->setPageTitle(Crud::PAGE_DETAIL, 'crud.team_members.detail.title')
            ->setSearchFields(['firstName', 'lastName', 'email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        if (!$this->isAdminOrManager()) {
            throw new AccessDeniedException();
        }

        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        return $this->applyTeamScope($qb);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('crud.team_members.tab.general')->hideOnIndex();
        yield FormField::addColumn(3);
        yield ImageField::new('profileImageUrl', '')
            ->setBasePath('uploads/profile_images')
            ->hideOnForm();

        yield FormField::addColumn(9);
        yield TextField::new('firstName', 'crud.team_members.field.first_name');
        yield TextField::new('lastName', 'crud.team_members.field.last_name');
        yield TextField::new('email', 'crud.team_members.field.email');
        yield ArrayField::new('roles', 'crud.team_members.field.role')
            ->formatValue(fn (array $roles, User $user): string => $this->roleTranslator->translate($roles));
        yield DateField::new('contractStartedAt', 'crud.team_members.field.start_date')
            ->hideOnForm();
        yield AssociationField::new('manager', 'crud.team_members.field.manager')
            ->formatValue(fn (?User $manager): string => null === $manager ? '—' : sprintf('%s %s', $manager->firstName, $manager->lastName))
            ->onlyOnDetail();

        yield FormField::addTab('crud.team_members.tab.leave_balance')->hideOnIndex();
        yield NumberField::new('annualLeaveAllowance', 'crud.team_members.field.annual_allowance')
            ->hideOnIndex()
            ->setDisabled();
        yield NumberField::new('currentLeaveBalance', 'crud.team_members.field.current_balance')
            ->hideOnIndex()
            ->setDisabled();
        yield DateField::new('absenceBalanceResetDay', 'crud.team_members.field.balance_reset_day')
            ->hideOnIndex()
            ->setDisabled();

        yield FormField::addTab('crud.team_members.tab.details')->hideOnIndex();
        yield DateField::new('birthDate', 'crud.team_members.field.birth_date')
            ->hideOnIndex()
            ->setDisabled();
        yield ChoiceField::new('workingDays', 'crud.team_members.field.working_days')
            ->setChoices(UserSettings::WORKING_DAYS)
            ->allowMultipleChoices()
            ->renderExpanded()
            ->hideOnIndex()
            ->setDisabled();
        yield BooleanField::new('isActive', 'crud.team_members.field.active')
            ->hideOnIndex()
            ->setDisabled()
            ->renderAsSwitch(false);
    }

    private function applyTeamScope(QueryBuilder $qb): QueryBuilder
    {
        if ($this->isAdmin()) {
            return $qb;
        }

        $user = $this->getUser();

        return $qb
            ->andWhere('entity.manager = :manager')
            ->setParameter('manager', $user);
    }
}
