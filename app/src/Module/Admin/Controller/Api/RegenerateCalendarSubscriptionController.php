<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\Api;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Service\Ical\IcalHashGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/api/user/calendar/regenerate', name: 'app_api_calendar_regenerate', methods: ['POST'])]
class RegenerateCalendarSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(env: 'ICAL_SECRET')]
        private readonly string $icalSecret,
    ) {
    }

    public function __invoke(
        #[CurrentUser]
        User $user,
        Request $request,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('calendar_regenerate', $this->extractToken($request))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $userId = $user->id->toString();
        $this->userFacade->regenerateCalendarSubscription($userId);

        $userDTO = $this->userFacade->getUser($userId);

        $url = $this->urlGenerator->generate('app_api_ical_endpoint', [
            'userId' => $userId,
            'secret' => IcalHashGenerator::generateForUser($userDTO, $this->icalSecret),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse(['success' => true, 'url' => $url]);
    }

    private function extractToken(Request $request): ?string
    {
        $token = $request->request->get('_token') ?? $request->query->get('_token');

        return is_string($token) ? $token : null;
    }
}
