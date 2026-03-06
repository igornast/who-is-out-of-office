<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\DTO\ChangePasswordDTO;
use App\Module\Admin\Form\ChangePasswordFormType;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/settings/account-security', name: 'app_settings_account_security')]
class AccountSecurityController extends AbstractController
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

        $dto = new ChangePasswordDTO();
        $form = $this->createForm(ChangePasswordFormType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->passwordHasher->isPasswordValid($user, $dto->currentPassword)) {
                $this->addFlash('danger', 'settings.account_security.error.current_password_invalid');

                return $this->redirectToRoute('app_settings_account_security');
            }

            $this->userFacade->changePassword($user->id->toString(), $dto->newPassword, $user);

            $this->addFlash('success', 'settings.account_security.success.password_changed');

            return $this->redirectToRoute('app_settings_account_security');
        }

        return $this->render('@AppAdmin/settings/account_security.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }
}
