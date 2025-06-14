<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\DTO\Slack\InteractiveNotificationDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SendChangeConfirmationToAbsenceChannelCommandHandler
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function handle(LeaveRequestDTO $leaveRequestDTO, InteractiveNotificationDTO $notificationDTO): void
    {
        if (null === $notificationDTO->responseUrl) {
            return;
        }

        $userDTO = $leaveRequestDTO->user;

        $this->httpClient->request(
            method: Request::METHOD_POST,
            url: $notificationDTO->responseUrl,
            options: [
                'json' => [
                    'text' => sprintf(
                        '%s, absence for %s %s (%s - %s).',
                        $notificationDTO->status->name,
                        $userDTO->firstName,
                        $userDTO->lastName,
                        $leaveRequestDTO->startDate->format('M d, Y'),
                        $leaveRequestDTO->endDate->format('M d, Y')
                    ),
                    'replace_original' => true,
                ],
            ]
        );
    }
}
