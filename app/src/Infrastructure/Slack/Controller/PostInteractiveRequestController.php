<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Controller;

use App\Infrastructure\Slack\Service\RequestVerifier;
use App\Shared\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\Facade\SlackFacadeInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/slack/interactive-endpoint', name: 'app_api_slack_interactive_endpoint', methods: ['POST'])]
class PostInteractiveRequestController extends AbstractController
{
    public function __construct(
        private readonly RequestVerifier $requestVerifier,
        private readonly SlackFacadeInterface $slackFacade,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (false === $this->requestVerifier->isValid($request)) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        $data = $request->getPayload()->all();

        $notificationDTO = InteractiveNotificationDTO::fromArray($data);

        $this->slackFacade->handleInteractiveNotification($notificationDTO);

        return new JsonResponse(null);

    }
}
