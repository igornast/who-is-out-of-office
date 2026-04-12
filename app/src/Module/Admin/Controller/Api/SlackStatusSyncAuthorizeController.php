<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/app/settings/slack-status-sync/oauth/authorize', name: 'app_api_slack_status_sync_authorize', methods: ['GET'])]
class SlackStatusSyncAuthorizeController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'SLACK_CLIENT_ID')]
        private readonly string $clientId,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $state = bin2hex(random_bytes(32));
        $request->getSession()->set('slack_oauth_state', $state);

        $redirectUri = $this->urlGenerator->generate(
            'app_api_slack_status_sync_callback',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $url = 'https://slack.com/oauth/v2/authorize?'.http_build_query([
            'client_id' => $this->clientId,
            'user_scope' => 'users.profile:write',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);

        return new RedirectResponse($url);
    }
}
