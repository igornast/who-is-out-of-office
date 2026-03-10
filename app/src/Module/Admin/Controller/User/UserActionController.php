<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\User;

use App\Shared\DTO\UserDTO;
use App\Shared\Facade\EmailFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\ExceptionInterface as MessengerExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
class UserActionController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly EmailFacadeInterface $emailFacade,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/app/user/{id}/reset-password', name: 'app_user_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request, string $id): JsonResponse
    {
        $userDTO = $this->userFacade->getUser($id);

        if (!$userDTO instanceof UserDTO) {
            throw new NotFoundHttpException(sprintf('User "%s" not found.', $id));
        }

        $token = $request->request->getString('_token');
        if (!$this->isCsrfTokenValid(sprintf('resetPassword%s', $id), $token)) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('user.action.error.invalid_csrf', domain: 'admin'),
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$userDTO->isActive) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('user.action.error.user_inactive', domain: 'admin'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $resetToken = $this->userFacade->createPasswordResetToken($userDTO->email);

        if (null === $resetToken) {
            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('user.action.error.reset_failed', domain: 'admin'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->emailFacade->sendPasswordResetEmail($userDTO->email, $resetToken);
        } catch (MessengerExceptionInterface $e) {
            $this->logger->error(sprintf('Failed to send admin-triggered password reset email for user %s: %s', $id, $e->getMessage()));

            return $this->json([
                'success' => false,
                'message' => $this->translator->trans('user.action.error.email_failed', domain: 'admin'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('user.action.success.reset_password', domain: 'admin'),
        ]);
    }
}
