<?php

declare(strict_types=1);

namespace App\Module\Admin\EventSubscriber;

use App\Module\Admin\Controller\DashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ControllerFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AdminContextForCustomRoutesSubscriber implements EventSubscriberInterface
{
    private const array CUSTOM_ROUTES = [
        'app_user_profile',
        'app_calendar_view',
        'app_settings',
        'app_settings_notifications',
        'app_settings_integrations',
        'app_settings_account_security',
        'app_settings_appearance',
    ];

    public function __construct(
        private readonly AdminContextFactory $adminContextFactory,
        private readonly ControllerFactory $controllerFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -1],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (!\in_array($route, self::CUSTOM_ROUTES, true)) {
            return;
        }

        if (null !== $request->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE)) {
            return;
        }

        $dashboardController = $this->controllerFactory->getDashboardControllerInstance(
            DashboardController::class,
            $request,
        );

        if (null === $dashboardController) {
            return;
        }

        $adminContext = $this->adminContextFactory->create($request, $dashboardController, null);
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $adminContext);
    }
}
