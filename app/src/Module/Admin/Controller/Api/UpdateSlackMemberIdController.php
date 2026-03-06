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
#[Route('/app/api/user/slack/update', name: 'app_api_slack_update', methods: ['POST'])]
class UpdateSlackMemberIdController extends AbstractController
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
        if (!$this->isCsrfTokenValid('slack_update', $this->extractToken($request))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $slackMemberId = trim((string) $request->request->get('slack_member_id'));
        if ('' === $slackMemberId) {
            return new JsonResponse(['success' => false, 'message' => 'Slack Member ID is required.'], Response::HTTP_BAD_REQUEST);
        }

        $this->userFacade->updateSlackMemberId($user->id->toString(), $slackMemberId);

        return new JsonResponse([
            'success' => true,
            'connected' => true,
            'memberId' => $slackMemberId,
        ]);
    }

    private function extractToken(Request $request): ?string
    {
        $token = $request->request->get('_token');

        return is_string($token) ? $token : null;
    }
}
