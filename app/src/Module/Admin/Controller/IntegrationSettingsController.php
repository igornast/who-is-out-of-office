<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/settings/integrations', name: 'app_settings_integrations')]
#[IsGranted('ROLE_ADMIN')]
class IntegrationSettingsController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'SLACK_DSN')]
        private readonly string $slackDsn,
        #[Autowire(env: 'ICAL_SECRET')]
        private readonly string $icalSecret,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('@AppAdmin/settings/integrations.html.twig', [
            'slack_connected' => '' !== $this->slackDsn,
            'ical_enabled' => '' !== $this->icalSecret,
            'date_nager_enabled' => true,
        ]);
    }
}
