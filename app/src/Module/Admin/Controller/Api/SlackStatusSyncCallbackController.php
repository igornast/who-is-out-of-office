<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\Api;

use App\Infrastructure\Slack\Exception\SlackStatusApiException;
use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/app/settings/slack-status-sync/oauth/callback', name: 'app_api_slack_status_sync_callback', methods: ['GET'])]
class SlackStatusSyncCallbackController extends AbstractController
{
    public function __construct(
        private readonly SlackFacadeInterface $slackFacade,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $expectedState = $request->getSession()->get('slack_oauth_state');
        $receivedState = $request->query->get('state');
        $code = $request->query->get('code');

        $request->getSession()->remove('slack_oauth_state');

        if (!is_string($expectedState) || !is_string($receivedState) || !hash_equals($expectedState, $receivedState)) {
            $this->addFlash('danger', $this->translator->trans('slack_status_sync.oauth.invalid_state', domain: 'admin'));

            return $this->redirectToSettings();
        }

        if (!is_string($code) || '' === $code) {
            $errorReason = (string) $request->query->get('error', 'no_code');
            $this->addFlash('danger', $this->translator->trans('slack_status_sync.oauth.failed', ['%reason%' => $errorReason], 'admin'));

            return $this->redirectToSettings();
        }

        $redirectUri = $this->urlGenerator->generate(
            'app_api_slack_status_sync_callback',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        try {
            $this->slackFacade->storeAdminToken($code, $redirectUri);
            $this->addFlash('success', $this->translator->trans('slack_status_sync.oauth.connected', domain: 'admin'));
        } catch (SlackStatusApiException $e) {
            $this->addFlash('danger', $this->translator->trans('slack_status_sync.oauth.failed', ['%reason%' => $e->slackError], 'admin'));
        }

        return $this->redirectToSettings();
    }

    private function redirectToSettings(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('app_settings_integrations'));
    }
}
