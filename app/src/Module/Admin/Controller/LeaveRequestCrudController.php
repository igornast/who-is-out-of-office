<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Module\Admin\Validator\EndDateHasWorkdays;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\LeaveRequestTypeEnum;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

#[AdminCrud(routePath: '/leave-request', routeName: 'app_leave_request')]
class LeaveRequestCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LeaveRequest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setSearchFields(null)
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DELETE, Action::EDIT);
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

        if ($this->isGranted('ROLE_ADMIN')) {
            return $qb;
        }

        return $qb->andWhere('entity.user = :user')->setParameter('user', $this->getUser());
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addColumn(8),
            FormField::addPanel('Request absence'),
            ChoiceField::new('leaveType', 'What kind of absence')
                ->setFormTypeOptions(['constraints' => [
                    new NotBlank(['message' => 'Please choose a leave type.']),
                ]]),
            DateField::new('startDate', 'From')
                ->setColumns(4)
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
                ->setColumns(4)
                ->setFormTypeOptions([
                    'constraints' => [
                        new NotBlank(['message' => 'End date cannot be blank.']),
                        new Expression([
                            'expression' => 'value >= context.getObject().getParent().getData().startDate',
                            'message' => 'The end date must be equal or later than the start date.',
                        ]),
                        new EndDateHasWorkdays(),
                    ],
                ]),

            FormField::addColumn(4)->hideWhenCreating(),
            FormField::addPanel('Status')->hideWhenCreating(),
            ChoiceField::new('status')->setDisabled()->hideWhenCreating(),
            NumberField::new('workDays')->setDisabled()->hideWhenCreating(),
            DateField::new('createdAt')->onlyOnIndex(),
        ];
    }
}
