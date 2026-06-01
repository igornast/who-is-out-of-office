<?php

declare(strict_types=1);

namespace App\Infrastructure\Ical\Controller;

use App\Infrastructure\Ical\Service\CalendarRequestVerifier;
use App\Infrastructure\Ical\Service\IcalCalendarBuilder;
use App\Shared\DTO\CalendarSubscription\CalendarSubscriptionCandidateDTO;
use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/calendar/{userId}/{secret}.ics', name: 'app_api_ical_endpoint', methods: ['GET'])]
class CalendarExportController extends AbstractController
{
    public function __construct(
        private readonly CalendarRequestVerifier $calendarRequestVerifier,
        private readonly IcalCalendarBuilder $calendarBuilder,
        private readonly UserFacadeInterface $userFacade,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly HolidayFacadeInterface $holidayFacade,
    ) {
    }

    public function __invoke(string $userId, string $secret): Response
    {
        $userDTO = $this->userFacade->getUser($userId);

        if (null === $userDTO || !$this->calendarRequestVerifier->isValid($userDTO, $secret)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $start = new \DateTimeImmutable()->modify('-1 month');
        $end = new \DateTimeImmutable()->modify('+1 year');

        $config = $this->userFacade->getCalendarSubscriptionConfig($userId);

        $teamMemberIds = $config->selectedTeamMemberIds
            ?? array_map(fn (CalendarSubscriptionCandidateDTO $c) => $c->id, $config->candidateTeamMembers);

        $holidayCalendarIds = $config->selectedHolidayCalendarIds
            ?? array_map(fn (PublicHolidayCalendarDTO $c) => $c->id->toString(), $config->candidateHolidayCalendars);

        $teamMemberIds = array_values(array_unique([$userId, ...$teamMemberIds]));

        $leaveRequests = $this->leaveRequestFacade->getLeaveRequestsForUsersBetween(
            $teamMemberIds,
            [LeaveRequestStatusEnum::Approved],
            $start,
            $end,
        );

        $holidays = [] === $holidayCalendarIds
            ? []
            : $this->holidayFacade->getHolidaysForCalendarsBetween($holidayCalendarIds, $start, $end);

        $calendarComponent = $this->calendarBuilder->build($leaveRequests, $holidays);

        return new Response((string) $calendarComponent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cal.ics"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
