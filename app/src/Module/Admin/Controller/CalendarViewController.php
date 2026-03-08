<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\DTO\UserDTO;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Service\Ical\IcalSubscriptionUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/calendar', name: 'app_calendar_view')]
class CalendarViewController extends AbstractController
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly IcalSubscriptionUrlGenerator $icalSubscriptionUrlGenerator,
    ) {
    }

    public function __invoke(#[CurrentUser] User $user): Response
    {
        return $this->render('@AppAdmin/calendar_view.html.twig', [
            'calendar_subscription_url' => $this->icalSubscriptionUrlGenerator->generateForUser(UserDTO::fromEntity($user)),
            'leave_types' => $this->leaveRequestFacade->getAllLeaveTypes(),
        ]);
    }
}
