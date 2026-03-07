<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\AppearanceSettingsDTO;
use App\Module\Admin\Form\AppearanceSettingsFormType;
use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/settings/appearance', name: 'app_settings_appearance')]
class AppearanceSettingsController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $dto = new AppearanceSettingsDTO(
            theme: ThemeEnum::tryFrom($user->themePreference) ?? ThemeEnum::Auto,
            palette: PaletteEnum::tryFrom($user->palettePreference) ?? PaletteEnum::Teal,
        );

        $form = $this->createForm(AppearanceSettingsFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userFacade->updateThemePreference(
                $user->id->toString(),
                $dto->theme,
                $dto->palette,
            );

            $this->addFlash('success', 'settings.appearance.success.saved');

            return $this->redirectToRoute('app_settings_appearance');
        }

        return $this->render('@AppAdmin/settings/appearance.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
