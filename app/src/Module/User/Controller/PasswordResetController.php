<?php

declare(strict_types=1);

namespace App\Module\User\Controller;

use App\Module\User\DTO\PasswordResetDTO;
use App\Module\User\Form\PasswordResetType;
use App\Shared\DTO\PasswordResetTokenDTO;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/password-reset/{token}', name: 'app_password_reset', methods: ['GET', 'POST'])]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(string $token, Request $request): Response
    {
        $tokenDTO = $this->userFacade->getPasswordResetToken($token);

        if (!$tokenDTO instanceof PasswordResetTokenDTO || $tokenDTO->isExpired()) {
            throw new NotFoundHttpException();
        }

        $form = $this->createForm(PasswordResetType::class, new PasswordResetDTO());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var PasswordResetDTO $dto */
            $dto = $form->getData();

            $success = $this->userFacade->resetPassword($token, $dto->password);

            if ($success) {
                $this->addFlash('success', $this->translator->trans('password_reset.reset.success'));

                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('error', $this->translator->trans('password_reset.reset.error'));

            return $this->redirectToRoute('app_password_reset_request');
        }

        return $this->render('@AppUser/password_reset.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
