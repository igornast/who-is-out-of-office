<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\AppAbstractCrudController;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @extends AppAbstractCrudController<LeaveRequest>
 */
#[AdminRoute(path: '/team/leave-requests', name: 'app_team_leave_requests')]
class TeamLeaveRequestCrudController extends AppAbstractCrudController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return LeaveRequest::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $approveAction = Action::new('approve', 'Approve')
            ->linkToUrl(
                fn (LeaveRequest $entity) => $this->urlGenerator->generate('app_leave_request_approve', [
                    'id' => $entity->id,
                    '_token' => $this->csrfTokenManager->getToken(sprintf('approve%s', $entity->id))->getValue(),
                ])
            )
            ->renderAsForm()
            ->setIcon('icon-check')
            ->addCssClass('btn btn-success')
            ->displayIf(fn (LeaveRequest $request) => LeaveRequestStatusEnum::Pending === $request->status);

        $rejectAction = Action::new('reject', 'Reject')
            ->linkToUrl(
                fn (LeaveRequest $entity) => $this->urlGenerator->generate('app_leave_request_reject', [
                    'id' => $entity->id,
                    '_token' => $this->csrfTokenManager->getToken(sprintf('reject%s', $entity->id))->getValue(),
                ])
            )
            ->renderAsForm()
            ->setIcon('icon-x')
            ->addCssClass('btn btn-danger')
            ->displayIf(fn (LeaveRequest $request) => LeaveRequestStatusEnum::Pending === $request->status);

        return $actions
            ->add(Crud::PAGE_DETAIL, $approveAction)
            ->add(Crud::PAGE_DETAIL, $rejectAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Action::INDEX, 'crud.title.team_leave_requests')
            ->setSearchFields(['user.firstName', 'user.lastName', 'status', 'leaveType.name'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(LeaveRequestStatusEnum::getChoices()))
            ->add('user')
            ->add('startDate')
            ->add('endDate')
            ->add('leaveType');
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
            ->join('entity.user', 'u')
            ->andWhere('u.manager = :manager')
            ->setParameter('manager', $user);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addColumn(8),
            FormField::addFieldset('Request Details'),
            AssociationField::new('user', 'Employee')
                ->formatValue(fn (User $user, LeaveRequest $request): string => sprintf('%s %s', $user->firstName, $user->lastName)),

            AssociationField::new('leaveType', 'Type')
                ->formatValue(fn (LeaveRequestType $requestType, LeaveRequest $request): string => $requestType->name),

            DateField::new('startDate', 'From')
                ->setColumns(6),
            DateField::new('endDate', 'To')
                ->setColumns(6),

            FormField::addColumn(4)->hideWhenCreating(),
            FormField::addFieldset('Details')->hideWhenCreating(),
            ChoiceField::new('status')->setChoices(LeaveRequestStatusEnum::cases())->setDisabled()->hideWhenCreating(),
            NumberField::new('workDays')->setDisabled()->hideWhenCreating(),
            AssociationField::new('approvedBy')->setDisabled()->hideWhenCreating(),
            BooleanField::new('isAutoApproved')->setDisabled()->hideWhenCreating()->renderAsSwitch(false),
            DateField::new('createdAt')->setDisabled()->hideWhenCreating(),
        ];
    }
}
