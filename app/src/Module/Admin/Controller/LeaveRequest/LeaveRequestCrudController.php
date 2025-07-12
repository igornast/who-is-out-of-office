<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\AppAbstractCrudController;
use App\Module\Admin\Validator\HasWorkdaysAndBalance;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\LeaveRequestTypeEnum;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AppAbstractCrudController<LeaveRequest>
 */
#[AdminCrud(routePath: '/leave-request', routeName: 'app_leave_request')]
class LeaveRequestCrudController extends AppAbstractCrudController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
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
                fn (LeaveRequest $entity) => $this->urlGenerator->generate('app_leave_request_withdraw', ['id' => $entity->id])
            )
            ->setIcon('fa fa-ban')
            ->addCssClass('btn btn-outline')
            ->displayIf($this->shouldDisplayWithdrawAction());

        return $actions
            ->add(Crud::PAGE_DETAIL, $withdrawAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(
                Crud::PAGE_INDEX,
                Action::NEW,
                fn (Action $action) => $action->setIcon('fa fa-plus')->setLabel('crud.actions.leave_requests.new')
            )
            ->disable(Action::DELETE, Action::EDIT);
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud->setPageTitle(Action::INDEX, 'crud.title.leave_requests');

        if ($this->isAdmin()) {
            return $crud
                ->setSearchFields(['status', 'leaveType'])
                ->setDefaultSort(['createdAt' => 'DESC']);
        }

        return $crud
            ->setSearchFields(null)
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(LeaveRequestStatusEnum::getChoices()))
            ->add(ChoiceFilter::new('leaveType')->setChoices(LeaveRequestTypeEnum::getChoices()));
    }

    public function createEntity(string $entityFqcn): LeaveRequest
    {
        return new LeaveRequest(
            id: Uuid::uuid4(),
            user: $this->getUser(),
            status: LeaveRequestStatusEnum::Pending,
            leaveType: LeaveRequestTypeEnum::Vacation,
            startDate: new \DateTimeImmutable(),
            endDate: new \DateTimeImmutable('tomorrow'),
            workDays: 1,
        );
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
            AssociationField::new('user', 'Name')
                ->formatValue(fn (User $user, LeaveRequest $request): string => sprintf('%s %s', $user->firstName, $user->lastName))
                ->setPermission('ROLE_ADMIN'),
            ChoiceField::new('leaveType', 'What type of absence')
                ->setChoices(LeaveRequestTypeEnum::getChoices())
                ->setFormTypeOptions(['constraints' => [
                    new NotBlank(['message' => 'Please choose a leave type.']),
                ]]),
            DateField::new('startDate', 'From')
                ->setColumns(6)
                ->setFormTypeOptions([
                    'constraints' => [
                        new NotBlank(['message' => 'Start date cannot be blank.']),
                        new GreaterThanOrEqual([
                            'value' => new \DateTimeImmutable('today'),
                            'message' => 'The start date cannot be in the past.',
                        ]),
                    ],
                ]),
            DateField::new('endDate', 'To')
                ->setColumns(6)
                ->setFormTypeOptions([
                    'constraints' => [
                        new NotBlank(['message' => 'End date cannot be blank.']),
                        new Expression([
                            'expression' => 'value >= context.getObject().getParent().getData().startDate',
                            'message' => 'The end date must be equal or later than the start date.',
                        ]),
                        new HasWorkdaysAndBalance(),
                    ],
                ]),

            FormField::addColumn(4)->hideWhenCreating(),
            FormField::addFieldset('Details')->hideWhenCreating(),
            ChoiceField::new('status')->setChoices(LeaveRequestStatusEnum::cases())->setDisabled()->hideWhenCreating(),
            NumberField::new('workDays')->setDisabled()->hideWhenCreating(),
            DateField::new('createdAt')->onlyOnIndex(),
        ];
    }

    private function shouldDisplayWithdrawAction(): \Closure
    {
        return fn (LeaveRequest $request) => in_array($request->status, [LeaveRequestStatusEnum::Pending, LeaveRequestStatusEnum::Approved], true) && $this->getUser() === $request->user;
    }
}
