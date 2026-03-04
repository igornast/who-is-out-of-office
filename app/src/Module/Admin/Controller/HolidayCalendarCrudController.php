<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Shared\Enum\RoleEnum;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AppAbstractCrudController<HolidayCalendar>
 */
#[AdminRoute(path: '/settings/public-holidays', name: 'app_settings_holidays')]
class HolidayCalendarCrudController extends AppAbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return HolidayCalendar::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setPageTitle(Crud::PAGE_INDEX, 'crud.holiday_calendar.index.title')
            ->setPageTitle(Crud::PAGE_DETAIL, 'crud.holiday_calendar.detail.title')
            ->setEntityPermission(RoleEnum::Admin->value)
            ->setSearchFields(['countryName', 'countryCode']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('countryCode')->setLabel('crud.holiday_calendar.field.country_code');
        yield TextField::new('countryName')->setLabel('crud.holiday_calendar.field.country_name');
        yield AssociationField::new('holidays')
            ->setLabel('crud.holiday_calendar.field.holidays_count')
            ->onlyOnIndex();
        yield AssociationField::new('holidays')
            ->setLabel('crud.holiday_calendar.field.holidays_list')
            ->setTemplatePath('@AppAdmin/field/holidays_list.html.twig')
            ->onlyOnDetail();
    }
}
