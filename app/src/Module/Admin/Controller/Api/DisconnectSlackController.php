<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\Api;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/api/user/slack/disconnect', name: 'app_api_slack_disconnect', methods: ['POST'])]
class DisconnectSlackController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        User $user,
        Request $request,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('slack_disconnect', $this->extractToken($request))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $this->userFacade->disconnectSlack($user->id->toString());

        return new JsonResponse([
            'success' => true,
            'connected' => false,
        ]);
    }

    private function extractToken(Request $request): ?string
    {
        $token = $request->request->get('_token');

        return is_string($token) ? $token : null;
    }
}
