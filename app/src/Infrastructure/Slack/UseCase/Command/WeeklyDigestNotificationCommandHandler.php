<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

class WeeklyDigestNotificationCommandHandler
{
    public function __construct(
        #[Autowire(env: 'SLACK_AR_HR_DIGEST_CHANNEL_ID')]
        private readonly string $dailyDigestChannelId,
        private readonly ChatterInterface $chatter,
        private readonly UserFacadeInterface $userFacade,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    public function handle(): void
    {
        $today   = new \DateTimeImmutable()->setTime(0, 0, 0);
        $weekDay = (int) $today->format('N');
        $monday  = $today->modify('-'.($weekDay - 1).' days');
        $sunday  = $monday->modify('+6 days');

        $approvedRequests = $this->leaveRequestFacade->getApprovedLeaveRequestsForDates($monday, $sunday);
        $birthdayUsers = $this->userFacade->getUsersWithBirthdaysForDates($monday, $sunday);

        $oooSection = $this->generateWhoIsOutSection($approvedRequests);
        $birthdaysSection = $this->generateBirthdaysSection($birthdayUsers);
        $contextSection = $this->generateHeaderSection();

        if (0 === sizeof($oooSection) && 0 === sizeof($birthdaysSection)) {
            $options = new SlackOptions([
                'channel' => $this->dailyDigestChannelId,
                'blocks' => [
                    ...$contextSection,
                    ...$this->generateEmptyDigestMessage()
                ],
            ]);

            $this->chatter->send(new ChatMessage('Absences Weekly Digest')->options($options));

            return;
        }

        $options = new SlackOptions([
            'channel' => $this->dailyDigestChannelId,
            'blocks' => [
                ...$contextSection,
                ...$oooSection,
                ...$birthdaysSection,
            ],
        ]);

        $this->chatter->send(new ChatMessage('Absences Weekly Digest')->options($options));
    }

    /**
     * @param LeaveRequestDTO[] $leaveRequestDTOS
     */
    private function generateWhoIsOutSection(array $leaveRequestDTOS): array
    {
        if (0 === sizeof($leaveRequestDTOS)) {
            return [];
        }

        $text = '';
        foreach ($leaveRequestDTOS as $requestDTO) {
            $text .=  sprintf(
                "> *%s %s* - %s _(%s - %s)_\n",
                $requestDTO->user->firstName,
                $requestDTO->user->lastName,
                $requestDTO->leaveType->name,
                $requestDTO->startDate->format('F d'),
                $requestDTO->endDate->format('F d')
            );
        }

        return [
            ['type' => 'divider'],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '📆 | *Who is out this week? *']],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $text]],
        ];
    }

    /**
     * @param UserDTO[] $birthdayUserDTOs
     */
    private function generateBirthdaysSection(array $birthdayUserDTOs): array
    {

        if (0 === sizeof($birthdayUserDTOs)) {
            return [];
        }

        $text = '';
        foreach ($birthdayUserDTOs as $userDTO) {
            $text .=  sprintf(
                "> *%s %s* - %s\n",
                $userDTO->firstName,
                $userDTO->lastName,
                $userDTO->birthDate?->format('F d') ?? '',
            );
        }

        return [
            ['type' => 'divider'],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '🍰 | *Birthdays*']],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $text],
            ],
        ];
    }

    private function generateHeaderSection(): array
    {
        return[
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => '✨ Weekly digest',
                    'emoji' => true,
                ],
            ],
            [
                'type' => 'context',
                'elements' => [
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => sprintf('*%s* | %s', new \DateTimeImmutable()->format('F d, Y'), 'HR Announcements'),
                    ],
                ],
            ],
        ];
    }

    private function generateEmptyDigestMessage()
    {
        return[
            [
                'type' => 'context',
                'elements' => [
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "🎉 *Good news!*\nNo one is out this week and there are no birthdays to celebrate.\nLet’s make it a great, productive week! 🚀",
                    ],
                ],
            ],
        ];
    }
}
