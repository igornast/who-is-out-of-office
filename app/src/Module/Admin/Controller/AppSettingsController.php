<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Module\Admin\Form\AppSettingsFormType;
use App\Shared\Exception\AppSettingsWriteException;
use App\Shared\Facade\AppSettingsFacadeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/app/settings', name: 'app_settings')]
#[IsGranted('ROLE_ADMIN')]
class AppSettingsController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'resolve:APP_SETTINGS_FILE')]
        private readonly string $settingFilename,
        private readonly AppSettingsFacadeInterface $appSettingsFacade,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $settingsDTO = $this->appSettingsFacade->getAllSettings();
        $form = $this->createForm(AppSettingsFormType::class, $settingsDTO);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->appSettingsFacade->updateAllSettings($settingsDTO);
            } catch (AppSettingsWriteException $exception) {
                $this->logger->error('[SETTINGS][SAVE]: Failed to write app settings.', [
                    'exception' => $exception->getMessage(),
                ]);
                $this->addFlash('error', $this->translator->trans('crud.app_settings.flash.save_error', domain: 'admin'));

                return $this->render('@AppAdmin/settings/edit.html.twig', [
                    'app_settings_filename' => $this->settingFilename,
                    'form' => $form->createView(),
                ]);
            }

            $this->addFlash('success', 'Settings updated successfully!');

            return $this->redirectToRoute('app_settings');
        }

        return $this->render('@AppAdmin/settings/edit.html.twig', [
            'app_settings_filename' => $this->settingFilename,
            'form' => $form->createView(),
        ]);
    }
}
