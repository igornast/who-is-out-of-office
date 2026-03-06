<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Module\Admin\DTO\HolidayCalendarImportDTO;
use App\Module\Admin\Form\HolidayCalendarImportFormType;
use App\Shared\Facade\DateNagerInterface;
use App\Shared\Facade\HolidayFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/app/settings/public-holidays/import', name: 'app_settings_holidays_import')]
#[IsGranted('ROLE_ADMIN')]
class HolidayCalendarImportController extends AbstractController
{
    public function __construct(
        private readonly DateNagerInterface $dateNager,
        private readonly HolidayFacadeInterface $holidayFacade,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $countryChoices = $this->buildCountryChoices();
        $dto = new HolidayCalendarImportDTO(year: (int) new \DateTimeImmutable()->format('Y'));

        $form = $this->createForm(HolidayCalendarImportFormType::class, $dto, [
            'country_choices' => $countryChoices,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $parts = explode('|', $dto->country, 2);
            if (2 !== \count($parts)) {
                throw new \UnexpectedValueException(sprintf('Invalid country value format: %s', $dto->country));
            }
            [$countryCode, $countryName] = $parts;

            $this->holidayFacade->syncCalendar($countryCode, $countryName, $dto->year);

            $this->addFlash('success', $this->translator->trans('crud.holiday_import.flash.success', ['%country%' => $countryName, '%year%' => $dto->year], 'admin'));

            return $this->redirectToRoute('app_dashboard_app_settings_holidays_index');
        }

        return $this->render('@AppAdmin/settings/holiday_import.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function buildCountryChoices(): array
    {
        $availableCountries = $this->dateNager->getAvailableCountries();
        $existingCalendars = $this->holidayFacade->getAllCalendars();

        $existingCodes = [];
        foreach ($existingCalendars as $calendar) {
            $existingCodes[$calendar->countryCode] = true;
        }

        $choices = [];
        foreach ($availableCountries as $country) {
            if (isset($existingCodes[$country->countryCode])) {
                continue;
            }

            $label = sprintf('%s (%s)', $country->name, $country->countryCode);
            $choices[$label] = sprintf('%s|%s', $country->countryCode, $country->name);
        }

        ksort($choices);

        return $choices;
    }
}
