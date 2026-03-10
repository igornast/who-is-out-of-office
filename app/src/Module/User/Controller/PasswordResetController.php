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
use Psr\Clock\ClockInterface;

#[Route('/password-reset/{token}', name: 'app_password_reset', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{64}'])]
class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly TranslatorInterface $translator,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $token, Request $request): Response
    {
        $tokenDTO = $this->userFacade->getPasswordResetToken($token);

        if (!$tokenDTO instanceof PasswordResetTokenDTO || $tokenDTO->expiresAt < $this->clock->now()) {
            throw new NotFoundHttpException();
        }

        $dto = new PasswordResetDTO();
        $form = $this->createForm(PasswordResetType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
