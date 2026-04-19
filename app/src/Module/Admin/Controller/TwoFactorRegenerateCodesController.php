<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/settings/two-factor/regenerate-codes', name: 'app_two_factor_regenerate_codes', methods: ['POST'])]
class TwoFactorRegenerateCodesController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isTwoFactorEnabled) {
            return $this->redirectToRoute('app_settings_account_security');
        }

        if (!$this->isCsrfTokenValid('regenerate_backup_codes', $request->request->getString('_csrf_token'))) {
            $this->addFlash('danger', 'error.invalid_csrf_token');

            return $this->redirectToRoute('app_settings_account_security');
        }

        $password = $request->request->getString('_password');

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('danger', 'settings.two_factor.disable.error.invalid_password');

            return $this->redirectToRoute('app_settings_account_security');
        }

        $codes = $this->userFacade->regenerateBackupCodes($user->id->toString());
        $request->getSession()->set('2fa_recovery_codes', $codes);

        return $this->redirectToRoute('app_two_factor_recovery_codes');
    }
}
