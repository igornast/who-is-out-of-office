<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller;

use App\Module\Admin\DTO\IntegrationStatusDTO;
use App\Shared\DTO\Slack\SlackAdminTokenDTO;
use App\Shared\Facade\SlackFacadeInterface;
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
        private readonly SlackFacadeInterface $slackFacade,
        #[Autowire(env: 'SLACK_DSN')]
        private readonly string $slackDsn,
        #[Autowire(env: 'SLACK_SIGNING_SECRET')]
        private readonly string $slackSigningSecret,
        #[Autowire(env: 'SLACK_AR_APPROVE_CHANNEL_ID')]
        private readonly string $slackApproveChannelId,
        #[Autowire(env: 'SLACK_AR_HR_DIGEST_CHANNEL_ID')]
        private readonly string $slackHrDigestChannelId,
        #[Autowire(env: 'SLACK_CLIENT_ID')]
        private readonly string $slackClientId,
        #[Autowire(env: 'SLACK_CLIENT_SECRET')]
        private readonly string $slackClientSecret,
        #[Autowire(env: 'SLACK_TOKEN_ENCRYPTION_KEY')]
        private readonly string $slackTokenEncryptionKey,
        #[Autowire(env: 'ICAL_SECRET')]
        private readonly string $icalSecret,
    ) {
    }

    public function __invoke(): Response
    {
        $token = $this->slackFacade->getAdminToken();

        return $this->render('@AppAdmin/settings/integrations.html.twig', [
            'slack_notifications' => IntegrationStatusDTO::fromEnvVars([
                'SLACK_DSN' => $this->slackDsn,
                'SLACK_SIGNING_SECRET' => $this->slackSigningSecret,
                'SLACK_AR_APPROVE_CHANNEL_ID' => $this->slackApproveChannelId,
                'SLACK_AR_HR_DIGEST_CHANNEL_ID' => $this->slackHrDigestChannelId,
            ]),
            'slack_configured' => '' !== $this->slackDsn,
            'slack_signing_secret_set' => '' !== $this->slackSigningSecret,
            'slack_approve_channel_set' => '' !== $this->slackApproveChannelId,
            'slack_digest_channel_set' => '' !== $this->slackHrDigestChannelId,
            'slack_status_sync' => $this->buildSlackStatusSyncStatus($token),
            'ical_enabled' => '' !== $this->icalSecret,
            'date_nager_enabled' => true,
        ]);
    }

    private function buildSlackStatusSyncStatus(?SlackAdminTokenDTO $token): IntegrationStatusDTO
    {
        $envStatus = IntegrationStatusDTO::fromEnvVars([
            'SLACK_CLIENT_ID' => $this->slackClientId,
            'SLACK_CLIENT_SECRET' => $this->slackClientSecret,
            'SLACK_TOKEN_ENCRYPTION_KEY' => $this->slackTokenEncryptionKey,
        ]);

        if (IntegrationStatusDTO::STATUS_ACTIVE !== $envStatus->status) {
            return $envStatus;
        }

        if (null === $token) {
            return new IntegrationStatusDTO(IntegrationStatusDTO::STATUS_NEEDS_AUTH);
        }

        return new IntegrationStatusDTO(IntegrationStatusDTO::STATUS_ACTIVE);
    }
}
