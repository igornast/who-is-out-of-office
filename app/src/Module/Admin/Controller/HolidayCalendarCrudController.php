<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Shared\Enum\RoleEnum;
use App\Shared\Facade\HolidayFacadeInterface;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AppAbstractCrudController<HolidayCalendar>
 */
#[IsGranted('ROLE_ADMIN')]
#[AdminRoute(path: '/settings/public-holidays', name: 'app_settings_holidays')]
class HolidayCalendarCrudController extends AppAbstractCrudController
{
    public function __construct(
        private readonly HolidayFacadeInterface $holidayFacade,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

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
        $toggleAction = Action::new('toggleActive', 'crud.holiday_calendar.action.toggle_active')
            ->linkToCrudAction('toggleActive');

        $syncAction = Action::new('syncCalendar', 'crud.holiday_calendar.action.sync')
            ->linkToCrudAction('syncCalendar');

        $importAction = Action::new('importCalendar', 'crud.holiday_calendar.action.import')
            ->linkToUrl(fn () => $this->generateUrl('app_settings_holidays_import'))
            ->addCssClass('btn btn-primary')
            ->createAsGlobalAction();

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $toggleAction)
            ->add(Crud::PAGE_INDEX, $syncAction)
            ->add(Crud::PAGE_INDEX, $importAction)
            ->add(Crud::PAGE_DETAIL, $syncAction)
            ->disable(Action::NEW, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('countryCode')->setLabel('crud.holiday_calendar.field.country_code');
        yield TextField::new('countryName')->setLabel('crud.holiday_calendar.field.country_name');
        yield BooleanField::new('isActive')
            ->setLabel('crud.holiday_calendar.field.is_active')
            ->renderAsSwitch(false);
        yield IntegerField::new('lastSyncedYear')
            ->setLabel('crud.holiday_calendar.field.last_synced_year')
            ->onlyOnIndex();
        yield AssociationField::new('holidays')
            ->setLabel('crud.holiday_calendar.field.holidays_count')
            ->onlyOnIndex();
        yield AssociationField::new('holidays')
            ->setLabel('crud.holiday_calendar.field.holidays_list')
            ->setTemplatePath('@AppAdmin/field/holidays_list.html.twig')
            ->onlyOnDetail();
    }

    /**
     * @param AdminContext<HolidayCalendar> $context
     */
    public function toggleActive(AdminContext $context): Response
    {
        $calendar = $this->getCalendarFromContext($context);
        $newState = !$calendar->isActive;

        $this->holidayFacade->toggleCalendarActive($calendar->id->toString(), $newState);

        $flashKey = $newState ? 'crud.holiday_calendar.flash.activated' : 'crud.holiday_calendar.flash.deactivated';
        $this->addFlash('success', $this->translator->trans($flashKey, ['%country%' => $calendar->countryName], 'admin'));

        return $this->redirectToIndex();
    }

    /**
     * @param AdminContext<HolidayCalendar> $context
     */
    public function syncCalendar(AdminContext $context): Response
    {
        $calendar = $this->getCalendarFromContext($context);
        $year = (int) new \DateTimeImmutable()->format('Y');

        $this->holidayFacade->syncCalendar($calendar->countryCode, $calendar->countryName, $year);

        $this->addFlash('success', $this->translator->trans('crud.holiday_calendar.flash.synced', ['%country%' => $calendar->countryName, '%year%' => $year], 'admin'));

        return $this->redirectToIndex();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        try {
            $this->holidayFacade->deleteCalendar($entityInstance->id->toString());
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $this->translator->trans('crud.holiday_calendar.flash.delete_error', ['%country%' => $entityInstance->countryName], 'admin'));
        }
    }

    /**
     * @param AdminContext<HolidayCalendar> $context
     */
    private function getCalendarFromContext(AdminContext $context): HolidayCalendar
    {
        if (null !== $context->getCrud()) {
            $instance = $context->getEntity()->getInstance();
            if ($instance instanceof HolidayCalendar) {
                return $instance;
            }
        }

        $entityId = $context->getRequest()->query->get('entityId');
        if (null === $entityId || '' === $entityId) {
            throw new NotFoundHttpException('Holiday calendar not found.');
        }

        $calendar = $this->entityManager->find(HolidayCalendar::class, Uuid::fromString($entityId));
        if (!$calendar instanceof HolidayCalendar) {
            throw new NotFoundHttpException('Holiday calendar not found.');
        }

        return $calendar;
    }

    private function redirectToIndex(): Response
    {
        return $this->redirect(
            $this->container->get(AdminUrlGenerator::class)->setAction(Action::INDEX)->generateUrl()
        );
    }
}
