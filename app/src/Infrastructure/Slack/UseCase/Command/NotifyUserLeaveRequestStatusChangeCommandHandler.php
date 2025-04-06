<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Shared\DTO\LeaveRequestDTO;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

class NotifyUserLeaveRequestStatusChangeCommandHandler
{
    public function __construct(
        private readonly ChatterInterface $chatter,
    ) {
    }

    public function handle(LeaveRequestDTO $leaveRequestDTO): void
    {

        $slackMemberId = $leaveRequestDTO->user->slackMemberId;
        if (null === $slackMemberId) {
            return;
        }

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
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => sprintf('*Type:* %s', $leaveRequestDTO->leaveType->value),
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
                    ],
                ],
            ],
        ]);

        $this->chatter->send(new ChatMessage('Absence Request Update')->options($options));
    }
}
