<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\LeaveRequest;

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Module\Admin\Controller\AppAbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Ramsey\Uuid\Uuid;

/**
 * @extends AppAbstractCrudController<LeaveRequestType>
 */
#[AdminRoute(path: '/leave-request-type', name: 'app_leave_request_type')]
class LeaveRequestTypeCrudController extends AppAbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LeaveRequestType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'crud.absence_request_type.index.title')
            ->setPageTitle(Crud::PAGE_EDIT, 'crud.absence_request_type.edit.title')
            ->setPageTitle(Crud::PAGE_NEW, 'crud.absence_request_type.create.title')
            ->setPageTitle(Crud::PAGE_DETAIL, 'crud.absence_request_type.detail.title')
            ->setEntityPermission('ROLE_ADMIN');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name');
        yield TextField::new('icon');
        yield BooleanField::new('isAffectingBalance')->setDisabled(Action::INDEX === $pageName);
        yield ColorField::new('backgroundColor');
        yield ColorField::new('borderColor');
        yield ColorField::new('textColor');
    }

    public function createEntity(string $entityFqcn): LeaveRequestType
    {
        return new LeaveRequestType(
            id: Uuid::uuid4(),
            isAffectingBalance: true,
            name: '',
            backgroundColor: '',
            borderColor: '',
            textColor: '',
            icon: ' ',
        );
    }
}
