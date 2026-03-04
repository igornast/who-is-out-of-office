<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\DTO\UserDTO;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Service\Ical\IcalHashGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/app/calendar', name: 'app_calendar_view')]
class CalendarViewController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'ICAL_SECRET')]
        private readonly string $icalSecret,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    public function __invoke(#[CurrentUser] User $user): Response
    {
        $calendarSubscriptionUrl = $this->urlGenerator->generate('app_api_ical_endpoint', [
            'userId' => $user->id,
            'secret' => IcalHashGenerator::generateForUser(UserDTO::fromEntity($user), $this->icalSecret),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->render('@AppAdmin/calendar_view.html.twig', [
            'calendar_subscription_url' => $calendarSubscriptionUrl,
            'leave_types' => $this->leaveRequestFacade->getAllLeaveTypes(),
        ]);
    }
}
