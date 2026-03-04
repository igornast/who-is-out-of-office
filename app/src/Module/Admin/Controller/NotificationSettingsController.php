<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/settings/notifications', name: 'app_settings_notifications')]
#[IsGranted('ROLE_ADMIN')]
class NotificationSettingsController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'SLACK_DSN')]
        private readonly string $slackDsn,
        #[Autowire(env: 'SLACK_SIGNING_SECRET')]
        private readonly string $slackSigningSecret,
        #[Autowire(env: 'SLACK_AR_APPROVE_CHANNEL_ID')]
        private readonly string $slackApproveChannelId,
        #[Autowire(env: 'SLACK_AR_HR_DIGEST_CHANNEL_ID')]
        private readonly string $slackDigestChannelId,
    ) {
    }

    public function __invoke(): Response
    {
        return $this->render('@AppAdmin/settings/notifications.html.twig', [
            'slack_configured' => '' !== $this->slackDsn,
            'slack_signing_secret_set' => '' !== $this->slackSigningSecret,
            'slack_approve_channel_set' => '' !== $this->slackApproveChannelId,
            'slack_digest_channel_set' => '' !== $this->slackDigestChannelId,
        ]);
    }
}
