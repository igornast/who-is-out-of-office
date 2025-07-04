<?php

declare(strict_types=1);

namespace App\Infrastructure\Ical\Controller;

use App\Infrastructure\Ical\Service\CalendarRequestVerifier;
use App\Infrastructure\Ical\Service\LeaveRequestsTransformer;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/calendar/{userId}/{secret}.ics', name: 'app_api_ical_endpoint')]
class CalendarExportController extends AbstractController
{
    public function __construct(
        private readonly CalendarRequestVerifier $calendarRequestVerifier,
        private readonly LeaveRequestsTransformer $leaveRequestsTransformer,
        private readonly UserFacadeInterface $userFacade,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    public function __invoke(string $userId, string $secret): Response
    {
        $userDTO = $this->userFacade->getUser($userId);

        if (!$this->calendarRequestVerifier->isValid($userDTO, $secret)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }


        $leaveRequestDTOs = $this->leaveRequestFacade->getLeaveRequestsForDates(
            startDate: new \DateTimeImmutable()->modify('- 1 month'),
            endDate: new \DateTimeImmutable()->modify('+ 1 year'),
            statuses: [LeaveRequestStatusEnum::Approved]
        );
        $calendarComponent =  $this->leaveRequestsTransformer->transformToCalendar($leaveRequestDTOs);

        return new Response((string) $calendarComponent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="cal.ics"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
