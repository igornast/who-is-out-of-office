<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\AppAbstractCrudController;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
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
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
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
            ->displayIf(fn (LeaveRequest $request) => LeaveRequestStatusEnum::Pending === $request->status && $this->canManageRequest($request));

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
            ->displayIf(fn (LeaveRequest $request) => LeaveRequestStatusEnum::Pending === $request->status && $this->canManageRequest($request));

        $batchApproveAction = Action::new('batchApprove', 'Approve selected')
            ->linkToCrudAction('batchApprove')
            ->addCssClass('btn btn-success');

        $batchRejectAction = Action::new('batchReject', 'Reject selected')
            ->linkToCrudAction('batchReject')
            ->addCssClass('btn btn-danger');

        return $actions
            ->add(Crud::PAGE_DETAIL, $approveAction)
            ->add(Crud::PAGE_DETAIL, $rejectAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->addBatchAction($batchApproveAction)
            ->addBatchAction($batchRejectAction)
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

    /**
     * @param AdminContext<LeaveRequest>   $context
     * @param BatchActionDto<LeaveRequest> $batchActionDto
     */
    public function batchApprove(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if (!$this->isCsrfTokenValid('ea-batch-action-batchApprove', $batchActionDto->getCsrfToken())) {
            return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::INDEX)->generateUrl());
        }

        $approver = UserDTO::fromEntity($this->getUser());
        $processed = 0;

        foreach ($batchActionDto->getEntityIds() as $id) {
            $dto = $this->leaveRequestFacade->getById($id);

            if (null === $dto || LeaveRequestStatusEnum::Pending !== $dto->status) {
                continue;
            }

            if ($dto->user->id === $approver->id) {
                continue;
            }

            $dto->status = LeaveRequestStatusEnum::Approved;
            $dto->approvedBy = $approver;
            $this->leaveRequestFacade->update($dto);
            ++$processed;
        }

        $this->addFlash('success', sprintf('%d leave request(s) approved.', $processed));

        return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::INDEX)->generateUrl());
    }

    /**
     * @param AdminContext<LeaveRequest>   $context
     * @param BatchActionDto<LeaveRequest> $batchActionDto
     */
    public function batchReject(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if (!$this->isCsrfTokenValid('ea-batch-action-batchReject', $batchActionDto->getCsrfToken())) {
            return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::INDEX)->generateUrl());
        }

        $approver = UserDTO::fromEntity($this->getUser());
        $processed = 0;

        foreach ($batchActionDto->getEntityIds() as $id) {
            $dto = $this->leaveRequestFacade->getById($id);

            if (null === $dto || LeaveRequestStatusEnum::Pending !== $dto->status) {
                continue;
            }

            if ($dto->user->id === $approver->id) {
                continue;
            }

            $dto->status = LeaveRequestStatusEnum::Rejected;
            $dto->approvedBy = $approver;
            $this->leaveRequestFacade->updateAndRestoreBalanceIfNeeded($dto);
            ++$processed;
        }

        $this->addFlash('success', sprintf('%d leave request(s) rejected.', $processed));

        return $this->redirect($this->container->get(AdminUrlGenerator::class)->setAction(Action::INDEX)->generateUrl());
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
            AssociationField::new('approvedBy')
                ->formatValue(fn (?User $user): string => null === $user ? '—' : sprintf('%s %s', $user->firstName, $user->lastName))
                ->setDisabled()->hideWhenCreating(),
            BooleanField::new('isAutoApproved')->setDisabled()->hideWhenCreating()->renderAsSwitch(false),
            DateField::new('createdAt')->setDisabled()->hideWhenCreating(),
        ];
    }
}
