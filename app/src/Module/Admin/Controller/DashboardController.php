<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\RoleEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
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
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
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
            'is_manager' => $this->isGranted(RoleEnum::Manager->value),
            'my_team' => $this->userFacade->getTeamMembersForUserId($userId),
            'users_with_birthdays' => $this->userFacade->getUsersWithIncomingBirthdays(),
            'users_with_work_anniversaries' => $this->userFacade->getUsersWithIncomingWorkAnniversaries(),
            'pending_requests' => $this->leaveRequestFacade->getLeaveRequestsForUser(
                $userId,
                [LeaveRequestStatusEnum::Pending]
            ),
            'upcoming_absences_in_team' => $this->leaveRequestFacade->getUpcomingLeaveRequests(),
            'all_pending_requests_count' => $this->leaveRequestFacade->countAllPendingRequests(),
            'on_leave_today' => $this->leaveRequestFacade->countOnLeaveToday(),
            'absences_this_week' => $this->leaveRequestFacade->countAbsencesThisWeek(),
            'slack_integration' => $user->slackIntegration,
        ];

        return $this->render('@AppAdmin/dashboard.html.twig', $parameters);
    }

    private function isAdmin(): bool
    {
        return $this->isGranted(RoleEnum::Admin->value);
    }

    public function configureMenuItems(): iterable
    {
        $teamCrudLink = MenuItem::linkToCrud('menu.items.my_team', 'fa fa-user', User::class);
        $leaveRequestTypesCrudLink = MenuItem::linkToCrud('menu.items.leave_request_types', 'fa fa-calendar-day', LeaveRequestType::class);
        $appSettingsLink = MenuItem::linkToRoute('menu.items.app_settings', 'fa fa-gear', 'app_settings');

        return [
            MenuItem::linkToLogout('menu.items.logout', 'fa fa-right-from-bracket'),
            MenuItem::linkToDashboard('menu.items.dashboard', 'fa fa-home'),
            ...($this->isAdmin() ? [$teamCrudLink, $leaveRequestTypesCrudLink, $appSettingsLink] : []),
            MenuItem::linkToRoute('menu.items.profile', 'fa fa-user', 'app_user_profile'),
            MenuItem::linkToRoute('menu.items.calendar', 'fa fa-calendar', 'app_calendar_view'),
            MenuItem::linkToCrud('menu.items.absence_requests', 'fa fa-calendar-plus', LeaveRequest::class),
        ];
    }

    /**
     * @param User $user
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

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->overrideTemplate('layout', '@AppAdmin/layout.html.twig');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addAssetMapperEntry('app');
    }
}
