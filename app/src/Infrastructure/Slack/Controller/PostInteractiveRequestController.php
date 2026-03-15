<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Controller;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Infrastructure\Slack\Service\RequestVerifier;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\EmailFacadeInterface;
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
        private readonly EmailFacadeInterface $emailFacade,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (false === $this->requestVerifier->isValid($request)) {
            return new JsonResponse(null, Response::HTTP_BAD_REQUEST);
        }

        $data = $request->getPayload()->all();

        $notificationDTO = InteractiveNotificationDTO::fromArray($data);

        $leaveRequestDTO = $this->slackFacade->handleInteractiveNotification($notificationDTO);

        if ($leaveRequestDTO->isAutoApproved) {
            return new JsonResponse(null);
        }

        match ($leaveRequestDTO->status) {
            LeaveRequestStatusEnum::Approved => $this->emailFacade->sendLeaveRequestApprovedEmail($leaveRequestDTO),
            LeaveRequestStatusEnum::Rejected => $this->emailFacade->sendLeaveRequestRejectedEmail($leaveRequestDTO),
            default => null,
        };

        return new JsonResponse(null);
    }
}
