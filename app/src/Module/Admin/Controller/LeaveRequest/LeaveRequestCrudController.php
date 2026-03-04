<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\AppAbstractCrudController;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\RoleEnum;
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
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @extends AppAbstractCrudController<LeaveRequest>
 */
#[AdminRoute(path: '/leave-request', name: 'app_leave_request')]
class LeaveRequestCrudController extends AppAbstractCrudController
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
        $withdrawAction = Action::new('withdraw', 'Withdraw')
            ->linkToUrl(
                fn (LeaveRequest $entity) => $this->urlGenerator->generate('app_leave_request_withdraw', [
                    'id' => $entity->id,
                    '_token' => $this->csrfTokenManager->getToken(sprintf('withdraw%s', $entity->id))->getValue(),
                ])
            )
            ->renderAsForm()
            ->setIcon('icon-ban')
            ->addCssClass('btn btn-outline')
            ->displayIf($this->shouldDisplayWithdrawAction());

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
            ->displayIf($this->shouldDisplayApproveRejectAction());

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
            ->displayIf($this->shouldDisplayApproveRejectAction());

        return $actions
            ->add(Crud::PAGE_DETAIL, $withdrawAction)
            ->add(Crud::PAGE_DETAIL, $approveAction)
            ->add(Crud::PAGE_DETAIL, $rejectAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT)
            ->add(
                Crud::PAGE_INDEX,
                Action::new('new-request')
                    ->setLabel('crud.actions.leave_requests.new')
                    ->linkToRoute('app_dashboard_requests_new')
                    ->addCssClass('btn btn-success')
                    ->createAsGlobalAction()
            )
            ->disable(Action::DELETE, Action::EDIT);
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud->setPageTitle(Action::INDEX, 'crud.title.leave_requests');

        if ($this->isAdmin()) {
            return $crud
                ->setSearchFields(['user', 'status', 'leaveType'])
                ->setDefaultSort(['createdAt' => 'DESC']);
        }

        return $crud
            ->setSearchFields(['status', 'leaveType'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        if ($this->isAdmin()) {
            return $filters
                ->add(ChoiceFilter::new('status')->setChoices(LeaveRequestStatusEnum::getChoices()))
                ->add('user')
                ->add('startDate')
                ->add('endDate')
                ->add('leaveType');
        }

        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(LeaveRequestStatusEnum::getChoices()))
            ->add('leaveType');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        if ($this->isAdmin()) {
            return $qb;
        }

        return $qb->andWhere('entity.user = :user')->setParameter('user', $this->getUser());
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addColumn(8),
            FormField::addFieldset('Request absence'),
            AssociationField::new('user', 'Person')
                ->formatValue(fn (User $user, LeaveRequest $request): string => sprintf('%s %s', $user->firstName, $user->lastName))
                ->setPermission(RoleEnum::Admin->value),

            AssociationField::new('leaveType', 'Type of the absence')
                ->formatValue(fn (LeaveRequestType $requestType, LeaveRequest $request): string => $requestType->name),

            DateField::new('startDate', 'From')
                ->setColumns(6),
            DateField::new('endDate', 'To')
                ->setColumns(6),

            FormField::addColumn(4)->hideWhenCreating(),
            FormField::addFieldset('Details')->hideWhenCreating(),
            ChoiceField::new('status')->setChoices(LeaveRequestStatusEnum::cases())->setDisabled()->hideWhenCreating(),
            NumberField::new('workDays')->setDisabled()->hideWhenCreating(),
            AssociationField::new('approvedBy')
                ->formatValue(fn (?User $user): string => null === $user ? '—' : sprintf('%s %s', $user->firstName, $user->lastName))
                ->setDisabled()->hideWhenCreating(),
            BooleanField::new('isAutoApproved')->setDisabled()->hideWhenCreating()->renderAsSwitch(false),
            DateField::new('createdAt')->setDisabled()->hideWhenCreating(),
        ];
    }

    private function shouldDisplayWithdrawAction(): \Closure
    {
        return fn (LeaveRequest $request) => in_array($request->status, [LeaveRequestStatusEnum::Pending, LeaveRequestStatusEnum::Approved], true) && $this->getUser() === $request->user;
    }

    private function shouldDisplayApproveRejectAction(): \Closure
    {
        return fn (LeaveRequest $request) => LeaveRequestStatusEnum::Pending === $request->status && $this->canManageRequest($request);
    }
}
