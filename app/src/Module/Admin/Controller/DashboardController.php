<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\LeaveRequest\LeaveRequestCrudController;
use App\Module\Admin\Controller\LeaveRequest\TeamLeaveRequestCrudController;
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
        $isManager = $this->isGranted(RoleEnum::Manager->value);

        $teamUserIds = $this->getTeamUserIds($userId, $isManager);

        $parameters = [
            'user' => $user,
            'is_admin' => $this->isAdmin(),
            'is_manager' => $isManager,
            'dashboard_stats' => $this->leaveRequestFacade->getDashboardStats($teamUserIds),
            'my_team' => $this->userFacade->getTeamMembersForUserId($userId),
            'users_with_birthdays' => $this->userFacade->getUsersWithIncomingBirthdays(),
            'users_with_work_anniversaries' => $this->userFacade->getUsersWithIncomingWorkAnniversaries(),
            'pending_requests' => $this->leaveRequestFacade->getLeaveRequestsForUser(
                $userId,
                [LeaveRequestStatusEnum::Pending]
            ),
            'upcoming_absences_in_team' => $this->leaveRequestFacade->getUpcomingLeaveRequests($teamUserIds),
            'recent_requests' => $this->leaveRequestFacade->getRecentLeaveRequests(5, $teamUserIds),
            'recent_requests_total' => $this->leaveRequestFacade->countAllRequests($teamUserIds),
            'whos_out_today' => $this->leaveRequestFacade->findOnLeaveToday($teamUserIds),
            'slack_integration' => $user->slackIntegration,
            'leave_balances' => $this->leaveRequestFacade->getLeaveBalancesPerType(
                $userId,
                $user->absenceBalanceResetDay
            ),
        ];

        return $this->render('@AppAdmin/dashboard.html.twig', $parameters);
    }

    /**
     * @return string[]|null
     */
    private function getTeamUserIds(string $userId, bool $isManager): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        if (!$isManager) {
            return null;
        }

        $directReports = $this->userFacade->getDirectReports($userId);

        return array_map(fn ($userDto) => $userDto->id, $directReports);
    }

    private function isAdmin(): bool
    {
        return $this->isGranted(RoleEnum::Admin->value);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToLogout('menu.items.logout', 'fa fa-right-from-bracket');
        yield MenuItem::linkToDashboard('menu.items.dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('menu.items.absence_requests', 'fa fa-calendar-plus', LeaveRequest::class)
            ->setController(LeaveRequestCrudController::class);
        yield MenuItem::linkToRoute('menu.items.calendar', 'fa fa-calendar', 'app_calendar_view');

        if ($this->isGranted(RoleEnum::Manager->value)) {
            yield MenuItem::section('menu.section.my_team')->setPermission(RoleEnum::Manager->value);
            yield MenuItem::linkToCrud('menu.items.team_leave_requests', 'fa fa-calendar-check', LeaveRequest::class)
                ->setController(TeamLeaveRequestCrudController::class)
                ->setPermission(RoleEnum::Manager->value);
            yield MenuItem::linkToCrud('menu.items.team_members', 'fa fa-users', User::class)
                ->setController(TeamMembersCrudController::class)
                ->setPermission(RoleEnum::Manager->value);
        }

        if ($this->isAdmin()) {
            yield MenuItem::section('menu.section.organization')->setPermission(RoleEnum::Admin->value);
            yield MenuItem::linkToCrud('menu.items.my_team', 'fa fa-user', User::class);

            yield MenuItem::section('menu.section.settings')->setPermission(RoleEnum::Admin->value);
            yield MenuItem::linkToRoute('menu.items.app_settings', 'fa fa-gear', 'app_settings');
            yield MenuItem::linkToCrud('menu.items.leave_request_types', 'fa fa-calendar-day', LeaveRequestType::class);
            yield MenuItem::linkToRoute('menu.items.public_holidays', 'fa fa-calendar', 'app_dashboard_app_settings_holidays_index');
        }

        yield MenuItem::section('menu.section.account');
        yield MenuItem::linkToRoute('menu.items.profile', 'fa fa-user', 'app_user_profile');
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
