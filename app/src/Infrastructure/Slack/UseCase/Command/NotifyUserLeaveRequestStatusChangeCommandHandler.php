<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotifyUserLeaveRequestStatusChangeCommandHandler
{
    public function __construct(
        private readonly ChatterInterface $chatter,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(LeaveRequestDTO $leaveRequestDTO): void
    {

        $slackMemberId = $leaveRequestDTO->user->slackMemberId;
        if (empty($slackMemberId)) {
            return;
        }

        $approvedByText = $this->generateApprovedByText($leaveRequestDTO);


        $options = new SlackOptions([
            'channel' => $slackMemberId,
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'Absence request status change',
                        'emoji' => true,
                    ],
                ],
                [
                    'type' => 'divider',
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf('*Type:* %s', $leaveRequestDTO->leaveType->name),
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf(
                                '*Staus:* %s',
                                $leaveRequestDTO->status->name,
                            ),
                        ],
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf(
                                '*When:* %s - %s',
                                $leaveRequestDTO->startDate->format('M d, Y'),
                                $leaveRequestDTO->endDate->format('M d, Y')
                            ),
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => $approvedByText,
                        ],
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf(
                                '<%s|View the absence request>',
                                $this->urlGenerator->generate(
                                    'app_dashboard_app_leave_request_detail',
                                    ['entityId' => $leaveRequestDTO->id->toString()],
                                    UrlGeneratorInterface::ABSOLUTE_URL
                                ),
                            ),
                        ],
                    ],
                ],
            ],
        ]);

        $this->chatter->send(new ChatMessage('Absence Request Update')->options($options));
    }

    public function generateApprovedByText(LeaveRequestDTO $leaveRequestDTO): string
    {
        if (true === $leaveRequestDTO->isAutoApproved) {
            return '*By:* auto approved';
        }

        return $leaveRequestDTO->approvedBy ? sprintf(
            '*By:* %s %s',
            $leaveRequestDTO->approvedBy->firstName,
            $leaveRequestDTO->approvedBy->lastName,
        ) : '-';
    }
}
