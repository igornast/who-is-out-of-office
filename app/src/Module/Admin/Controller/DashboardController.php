<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\LeaveRequest\LeaveRequestFacade;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\UserFacadeInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/app/dashboard', routeName: 'app_dashboard')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        #[Autowire('%profile_images_base_path%')]
        private readonly string $profileImagesBasePath,
        private readonly LeaveRequestFacade $leaveRequestFacade,
        private readonly UserFacadeInterface $userFacade,
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
            'my_team' => $this->userFacade->getTeamMembersForUserId($userId),
            'users_with_birthdays' => $this->userFacade->getUsersWithIncomingBirthdays(),
            'pending_requests' => $this->leaveRequestFacade->getLeaveRequestsForUser(
                $userId,
                [LeaveRequestStatusEnum::Pending]
            ),
            'upcoming_absences_in_team' => $this->leaveRequestFacade->getUpcomingLeaveRequests(),
            'slack_integration' => $user->slackIntegration,
        ];

        return $this->render('@AppAdmin/dashboard.html.twig', $parameters);
    }

    private function isAdmin(): bool
    {
        return $this->isGranted('ROLE_ADMIN');
    }

    public function configureMenuItems(): iterable
    {
        $teamCrudLink = MenuItem::linkToCrud('My Team', 'fa fa-user', User::class);

        return [
            MenuItem::linkToLogout('Logout', 'fa fa-right-from-bracket'),
            MenuItem::linkToDashboard('Dashboard', 'fa fa-home'),
            ...($this->isAdmin() ? [$teamCrudLink] : []),
            MenuItem::linkToRoute('Profile Settings', 'fa fa-user', 'app_user_profile'),
            MenuItem::linkToRoute('Calendar', 'fa fa-calendar', 'app_calendar_view'),
            MenuItem::linkToCrud('Absence Requests', 'fa fa-calendar-plus', LeaveRequest::class),
        ];
    }

    /**
     * @param UserInterface|User $user
     */
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $profileImageUrl = $user->profileImageUrl;
        if (null === $profileImageUrl) {
            return parent::configureUserMenu($user);
        }

        if (false === str_starts_with($profileImageUrl, 'http://') && false === str_starts_with($profileImageUrl, 'https://')) {
            $profileImageUrl = sprintf('/%s/%s', $this->profileImagesBasePath, $profileImageUrl);
        }

        return parent::configureUserMenu($user)
            ->setAvatarUrl($profileImageUrl);
    }
}
