<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\Repository\LeaveRequestSlackNotificationRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use Symfony\Component\Notifier\Bridge\Slack\UpdateMessageSlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UpdateAutoApprovedSlackNotificationCommandHandler
{
    public function __construct(
        private readonly LeaveRequestSlackNotificationRepositoryInterface $slackNotificationRepository,
        private readonly ChatterInterface $chatter,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(LeaveRequestDTO $leaveRequestDTO): void
    {
        $notification = $this->slackNotificationRepository->findByLeaveRequestId($leaveRequestDTO->id->toString());

        if (null === $notification) {
            return;
        }

        $messageText = sprintf(
            'Already auto approved - %s %s (%s - %s). <%s|Details>',
            $leaveRequestDTO->user->firstName,
            $leaveRequestDTO->user->lastName,
            $leaveRequestDTO->startDate->format('M d, Y'),
            $leaveRequestDTO->endDate->format('M d, Y'),
            $this->urlGenerator->generate(
                'app_dashboard_app_leave_request_detail',
                ['entityId' => $leaveRequestDTO->id->toString()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        );

        $options = new UpdateMessageSlackOptions($notification->channelId, $notification->messageTs);

        $this->chatter->send(new ChatMessage($messageText)->options($options));
    }
}
