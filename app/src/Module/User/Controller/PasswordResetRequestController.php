<?php

declare(strict_types=1);

namespace App\Module\User\Controller;

use App\Module\User\DTO\PasswordResetRequestDTO;
use App\Module\User\Form\PasswordResetRequestType;
use App\Shared\Facade\EmailFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/password-reset/request', name: 'app_password_reset_request', methods: ['GET', 'POST'])]
class PasswordResetRequestController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly EmailFacadeInterface $emailFacade,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->createForm(PasswordResetRequestType::class, new PasswordResetRequestDTO());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var PasswordResetRequestDTO $dto */
            $dto = $form->getData();

            $token = $this->userFacade->createPasswordResetToken($dto->email);

            if (null !== $token) {
                try {
                    $this->emailFacade->sendPasswordResetEmail($dto->email, $token);
                } catch (\Throwable $e) {
                    $this->logger->error(sprintf('Failed to send password reset email: %s', $e->getMessage()));
                }
            }

            $this->addFlash('success', $this->translator->trans('password_reset.request.success'));

            return $this->redirectToRoute('app_password_reset_request');
        }

        return $this->render('@AppUser/password_reset_request.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
