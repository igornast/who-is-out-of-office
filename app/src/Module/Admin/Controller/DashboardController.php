<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\LeaveRequest\LeaveRequestFacade;
use App\Module\User\UserFacade;
use App\Shared\Enum\LeaveRequestStatusEnum;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/app/dashboard', routeName: 'app_dashboard')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly LeaveRequestFacade $leaveRequestFacade,
        private readonly UserFacade $userFacade,
    ) {
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Who\'s OOO')
            ->renderContentMaximized()
            ->setDefaultColorScheme('auto')
            ->setTranslationDomain('admin');
    }

    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userId = $user->id->toString();

        $parameters = [
            'user' => $user,
            'is_admin' => $this->isAdmin(),
            'my_team' => $this->userFacade->getMyTeamUsers($userId),
            'users_with_birthdays' => $this->userFacade->getUsersWithIncomingBirthdays(),
            'pending_requests' => $this->leaveRequestFacade->getLeaveRequestsForUser(
                $userId,
                [LeaveRequestStatusEnum::Pending]
            ),
            'upcoming_absences_in_team' => $this->leaveRequestFacade->getUpcomingLeaveRequests(),
        ];

        return $this->render('@AppAdmin/dashboard.html.twig', $parameters);
    }

    public function configureMenuItems(): iterable
    {
        $teamCrudLink = MenuItem::linkToCrud('My Team', 'fa fa-user', User::class);

        return [
            MenuItem::linkToLogout('Logout', 'fa fa-right-from-bracket'),
            MenuItem::linkToDashboard('Dashboard', 'fa fa-home'),
            ...($this->isAdmin() ? [$teamCrudLink] : []),
            MenuItem::linkToCrud('Absence Requests', 'fa fa-calendar-plus', LeaveRequest::class),
        ];
    }

    private function isAdmin(): bool
    {
        return $this->isGranted('ROLE_ADMIN');
    }
}
