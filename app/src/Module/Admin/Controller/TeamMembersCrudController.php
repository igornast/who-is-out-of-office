<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * @extends AppAbstractCrudController<User>
 */
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
            ->setPageTitle(Action::INDEX, 'crud.title.team_members')
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
        $user = $this->getUser();

        if ($this->isAdmin()) {
            return $qb;
        }

        return $qb
            ->andWhere('entity.manager = :manager')
            ->setParameter('manager', $user);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('General')->hideOnIndex();
        yield FormField::addColumn(3);
        yield ImageField::new('profileImageUrl', '')
            ->setBasePath('uploads/profile_images')
            ->hideOnForm();

        yield FormField::addColumn(9);
        yield TextField::new('firstName');
        yield TextField::new('lastName');
        yield TextField::new('email');
        yield ArrayField::new('roles')
            ->formatValue(fn (array $roles, User $user): string => $this->roleTranslator->translate($roles));
        yield DateField::new('contractStartedAt', 'Start Date')->hideOnForm();

        yield FormField::addTab('Leave Balance')->hideOnIndex();
        yield NumberField::new('annualLeaveAllowance')->hideOnIndex()->setDisabled();
        yield NumberField::new('currentLeaveBalance')->hideOnIndex()->setDisabled();
        yield DateField::new('absenceBalanceResetDay')->hideOnIndex()->setDisabled();

        yield FormField::addTab('Details')->hideOnIndex();
        yield DateField::new('birthDate')->hideOnIndex()->setDisabled();
        yield BooleanField::new('isActive')->hideOnIndex()->setDisabled()->renderAsSwitch(false);
    }
}
