<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Module\Admin\Form\AppSettingsFormType;
use App\Shared\Facade\AppSettingsFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/settings', name: 'app_settings')]
#[IsGranted('ROLE_ADMIN')]
class AppSettingsController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'resolve:APP_SETTINGS_FILE')]
        private readonly string $settingFilename,
        private readonly AppSettingsFacadeInterface $appSettingsFacade,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $settingsDTO = $this->appSettingsFacade->getAllSettings();
        $form = $this->createForm(AppSettingsFormType::class, $settingsDTO);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->appSettingsFacade->updateAllSettings($settingsDTO);

            $this->addFlash('success', 'Settings updated successfully!');

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('@AppAdmin/settings/edit.html.twig', [
            'app_settings_filename' => $this->settingFilename,
            'form' => $form->createView(),
        ]);
    }
}
