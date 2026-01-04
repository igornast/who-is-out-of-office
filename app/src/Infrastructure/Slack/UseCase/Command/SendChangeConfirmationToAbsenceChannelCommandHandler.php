<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SendChangeConfirmationToAbsenceChannelCommandHandler
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(LeaveRequestDTO $leaveRequestDTO, InteractiveNotificationDTO $notificationDTO): void
    {
        if (null === $notificationDTO->responseUrl) {
            return;
        }

        $userDTO = $leaveRequestDTO->user;

        $messageReply = sprintf(
            '%s, absence for %s %s (%s - %s).',
            $notificationDTO->status->name,
            $userDTO->firstName,
            $userDTO->lastName,
            $leaveRequestDTO->startDate->format('M d, Y'),
            $leaveRequestDTO->endDate->format('M d, Y')
        );

        if (!is_null($leaveRequestDTO->approvedBy)) {
            $messageReply .= sprintf(
                ' By: %s %s',
                $leaveRequestDTO->approvedBy->firstName,
                $leaveRequestDTO->approvedBy->lastName,
            );
        }

        if (true === $leaveRequestDTO->isAutoApproved) {
            $messageReply = sprintf(
                'Already auto approved - %s %s (%s - %s).',
                $userDTO->firstName,
                $userDTO->lastName,
                $leaveRequestDTO->startDate->format('M d, Y'),
                $leaveRequestDTO->endDate->format('M d, Y')
            );
        }

        $messageReply .= sprintf(
            ' <%s|Details>',
            $this->urlGenerator->generate(
                'app_dashboard_app_leave_request_detail',
                ['entityId' => $leaveRequestDTO->id->toString()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        );

        $this->httpClient->request(
            method: Request::METHOD_POST,
            url: $notificationDTO->responseUrl,
            options: [
                'json' => [
                    'text' => $messageReply,
                    'replace_original' => true,
                ],
            ]
        );
    }
}
