<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\Api;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Service\CalendarAvatarUrlResolver;
use App\Shared\DTO\CalendarSubscription\CalendarSubscriptionCandidateDTO;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/api/user/calendar/customize')]
class CustomizeCalendarSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly UserFacadeInterface $userFacade,
        private readonly CalendarAvatarUrlResolver $avatarUrlResolver,
    ) {
    }

    #[Route(name: 'app_api_calendar_customize_get', methods: ['GET'])]
    public function get(#[CurrentUser] User $user): JsonResponse
    {
        $config = $this->userFacade->getCalendarSubscriptionConfig($user->id->toString());

        return new JsonResponse([
            'candidateTeamMembers' => array_map(
                fn (CalendarSubscriptionCandidateDTO $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'email' => $c->email,
                    'isManager' => $c->isManager,
                    'reportIds' => $c->reportIds,
                    'avatarUrl' => $this->avatarUrlResolver->resolve($c->profileImageUrl),
                    'initials' => $c->initials,
                    'colorIndex' => $c->colorIndex,
                ],
                $config->candidateTeamMembers,
            ),
            'candidateHolidayCalendars' => array_map(
                fn (PublicHolidayCalendarDTO $c) => [
                    'id' => $c->id->toString(),
                    'countryCode' => $c->countryCode,
                    'countryName' => $c->countryName,
                ],
                $config->candidateHolidayCalendars,
            ),
            'topLevelTeamMemberIds' => $config->topLevelTeamMemberIds,
            'myTeamMemberIds' => $config->myTeamMemberIds,
            'selectedTeamMemberIds' => $config->selectedTeamMemberIds,
            'selectedHolidayCalendarIds' => $config->selectedHolidayCalendarIds,
        ]);
    }

    #[Route(methods: ['POST'], name: 'app_api_calendar_customize_save')]
    public function save(#[CurrentUser] User $user, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $token = is_string($payload['_token'] ?? null) ? $payload['_token'] : null;
        if (!$this->isCsrfTokenValid('calendar_customize', $token)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $teamAuto = (bool) ($payload['teamMemberIdsAuto'] ?? true);
        $holidayAuto = (bool) ($payload['holidayCalendarIdsAuto'] ?? true);

        $teamIds = $teamAuto
            ? null
            : array_values(array_filter((array) ($payload['teamMemberIds'] ?? []), 'is_string'));
        $holidayIds = $holidayAuto
            ? null
            : array_values(array_filter((array) ($payload['holidayCalendarIds'] ?? []), 'is_string'));

        $this->userFacade->updateCalendarSubscriptionConfig(
            $user->id->toString(),
            $teamIds,
            $holidayIds,
        );

        return new JsonResponse(['success' => true]);
    }
}
