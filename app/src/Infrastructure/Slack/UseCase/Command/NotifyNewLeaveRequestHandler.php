<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

class NotifyNewLeaveRequestHandler
{
    public function __construct(
        #[Autowire(env: 'SLACK_AR_APPROVE_CHANNEL_ID')]
        private readonly string $requestsApproveChannelId,
        private readonly ChatterInterface $chatter,
    ) {
    }

    public function handle(LeaveRequestDTO $leaveRequestDTO): void
    {
        $options = new SlackOptions([
            'channel' => $this->requestsApproveChannelId,
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => sprintf(
                            'New absence request from %s %s',
                            $leaveRequestDTO->user->firstName,
                            $leaveRequestDTO->user->lastName
                        ),
                        'emoji' => true,
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf('*Type:* %s', $leaveRequestDTO->leaveType->value),
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf(
                                '*Created by:* <example.com|%s %s>',
                                $leaveRequestDTO->user->firstName,
                                $leaveRequestDTO->user->lastName
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
                    ],
                ],
                [
                    'type' => 'actions',
                    'elements' => [
                        [
                            'type' => 'button',
                            'text' => ['type' => 'plain_text', 'text' => 'Approve', 'emoji' => true],
                            'style' => 'primary',
                            'value' => sprintf(
                                'leave-request_%s_%s',
                                LeaveRequestStatusEnum::Approved->value,
                                $leaveRequestDTO->id
                            ),
                        ],
                        [
                            'type' => 'button',
                            'text' => ['type' => 'plain_text', 'text' => 'Reject', 'emoji' => true],
                            'style' => 'danger',
                            'value' => sprintf(
                                'leave-request_%s_%s',
                                LeaveRequestStatusEnum::Rejected->value,
                                $leaveRequestDTO->id
                            ),
                        ],
                    ],
                ],
            ],
        ]);

        $this->chatter->send(new ChatMessage('New Absence Request')->options($options));
    }
}
