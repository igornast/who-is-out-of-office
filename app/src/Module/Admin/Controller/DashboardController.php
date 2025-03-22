<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/app/dashboard', routeName: 'app_dashboard')]
class DashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Who\'s OOO')
            ->renderContentMaximized()
            ->setDefaultColorScheme('auto');
    }

    public function index(): Response
    {
        return $this->render('@AppAdmin/dashboard.html.twig');
    }

    public function configureMenuItems(): iterable
    {
        return [
            //            MenuItem::linkToLogout('Logout', 'fa fa-exit'),
            MenuItem::linkToDashboard('Dashboard', 'fa fa-home'),
            MenuItem::linkToCrud('My Team', 'fa fa-user', User::class),
            MenuItem::linkToCrud('Leave Requests', 'fa fa-user', LeaveRequest::class),
        ];
    }
}
