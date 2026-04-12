<?php

declare(strict_types=1);

namespace App\Module\Admin\Controller\Api;

use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
#[Route('/app/settings/slack-status-sync/revoke', name: 'app_api_slack_status_sync_revoke', methods: ['POST'])]
class RevokeSlackStatusSyncController extends AbstractController
{
    public function __construct(
        private readonly SlackFacadeInterface $slackFacade,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $token = $request->request->get('_token');
        if (!is_string($token) || !$this->isCsrfTokenValid('slack_status_sync_revoke', $token)) {
            $this->addFlash('danger', $this->translator->trans('csrf.invalid', domain: 'admin'));

            return new RedirectResponse($this->urlGenerator->generate('app_settings_integrations'));
        }

        $this->slackFacade->revokeAdminToken();
        $this->addFlash('success', $this->translator->trans('slack_status_sync.oauth.revoked', domain: 'admin'));

        return new RedirectResponse($this->urlGenerator->generate('app_settings_integrations'));
    }
}
