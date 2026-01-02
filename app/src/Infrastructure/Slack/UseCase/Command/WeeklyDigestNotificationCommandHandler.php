<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\Service\UsersEventsProvider;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Service\Messaging\EmojisProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

class WeeklyDigestNotificationCommandHandler
{
    private const string SUBJECT_MESSAGE = 'Absences Weekly Digest';

    public function __construct(
        #[Autowire(env: 'SLACK_AR_HR_DIGEST_CHANNEL_ID')]
        private readonly string $dailyDigestChannelId,
        private readonly ChatterInterface $chatter,
        private readonly UserFacadeInterface $userFacade,
        private readonly UsersEventsProvider $usersEventsProvider,
    ) {
    }

    public function handle(): void
    {
        $today = new \DateTimeImmutable()->setTime(0, 0, 0);
        $weekDay = (int) $today->format('N');
        $monday = $today->modify('-'.($weekDay - 1).' days');
        $sunday = $monday->modify('+6 days');

        /** @var array{string, LeaveRequestDTO[]|UserPublicHolidaysDTO[]} $mergedEvents */
        $mergedEvents = $this->usersEventsProvider->provideMergedAbsencesPerUser($monday, $sunday);

        $birthdayUsers = $this->userFacade->getUsersWithBirthdaysForDates($monday, $sunday);
        $anniversaryUsers = $this->userFacade->getUsersWithWorkAnniversariesForDates($monday, $sunday);

        $oooSection = $this->generateWhoIsOutSection($mergedEvents);
        $birthdaysSection = $this->generateBirthdaysSection($birthdayUsers);
        $anniversariesSection = $this->generateWorkAnniversariesSection($anniversaryUsers);
        $contextSection = $this->generateHeaderSection();

        if (0 === sizeof($oooSection) && 0 === sizeof($birthdaysSection) && 0 === sizeof($anniversariesSection)) {
            $this->handleNoLeaveAndBirthdaysDigest($contextSection);

            return;
        }

        $options = new SlackOptions([
            'channel' => $this->dailyDigestChannelId,
            'blocks' => [
                ...$contextSection,
                ...$oooSection,
                ...$birthdaysSection,
                ...$anniversariesSection,
            ],
        ]);

        $this->chatter->send(new ChatMessage(self::SUBJECT_MESSAGE)->options($options));
    }

    /**
     * @param array{string, array{int, LeaveRequestDTO|UserPublicHolidaysDTO}}|void[] $events
     *
     * @return array{string, string|array{string, string}}|void[]
     */
    private function generateWhoIsOutSection(array $events): array
    {
        if (0 === sizeof($events)) {
            return [];
        }

        $text = '';
        /** @var array{int, LeaveRequestDTO|UserPublicHolidaysDTO} $userEvents */
        foreach ($events as $userEvents) {

            /** @var LeaveRequestDTO|UserPublicHolidaysDTO $firstAbsenceDTO */
            $firstAbsenceDTO = $userEvents[0];

            $user = $firstAbsenceDTO->user;
            $text .= sprintf(
                "*%s %s*\n",
                $user->firstName,
                $user->lastName,
            );

            /** @var LeaveRequestDTO|UserPublicHolidaysDTO $event */
            foreach ($userEvents as $event) {

                if ($event instanceof LeaveRequestDTO) {

                    $startDate = $event->startDate->format('F d');
                    $endDate = $event->endDate->format('F d');

                    $datesPart = $startDate !== $endDate
                        ? sprintf('%s - %s', $startDate, $endDate)
                        : $startDate;

                    $text .= sprintf(
                        "    ‣ %s %s _(%s)_\n",
                        $event->leaveType->icon,
                        $event->leaveType->name,
                        $datesPart,
                    );
                }

                if ($event instanceof UserPublicHolidaysDTO) {
                    foreach ($event->holidays as $holiday) {
                        $text .= sprintf(
                            "    ‣ Public holiday: _%s (%s)_ %s\n",
                            $holiday->date->format('F d'),
                            $holiday->description,
                            EmojisProvider::getFlagEmojiCode($holiday->countryCode),
                        );
                    }
                }
            }

            $text .= "\n";
        }

        return [
            ['type' => 'divider'],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => '📆 | *Who is out this week? *']],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => $text]],
        ];
    }

    /**
     * @param UserDTO[] $birthdayUserDTOs
     *
     * @return array{string, string|array{string, string}}|void[]
     */
    private function generateBirthdaysSection(array $birthdayUserDTOs): array
    {

        if (0 === sizeof($birthdayUserDTOs)) {
            return [];
        }

        $text = '';
        foreach ($birthdayUserDTOs as $userDTO) {
            $text .= sprintf(
                "    ‣ *%s %s* - %s\n",
                $userDTO->firstName,
                $userDTO->lastName,
                $userDTO->birthDate?->format('F d') ?? '',
            );
        }

        return [
            ['type' => 'divider'],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "🍰 | *Birthdays*\n\n".$text]],
        ];
    }

    /**
     * @param UserDTO[] $anniversaryUserDTOs
     *
     * @return array<array{type: string, text?: array{type: string, text: string}}>
     */
    private function generateWorkAnniversariesSection(array $anniversaryUserDTOs): array
    {
        $text = '';
        foreach ($anniversaryUserDTOs as $userDTO) {
            if (null === $userDTO->contractStartedAt) {
                continue;
            }

            $currentYear = (int) new \DateTimeImmutable()->format('Y');
            $startYear = (int) $userDTO->contractStartedAt->format('Y');
            $years = $currentYear - $startYear;

            $yearText = $years ? sprintf(' (%d %s)', $years, 1 === $years ? 'year' : 'years') : '';

            $text .= sprintf(
                "    ‣ *%s %s* - %s%s\n",
                $userDTO->firstName,
                $userDTO->lastName,
                $userDTO->contractStartedAt->format('F d'),
                $yearText,
            );
        }

        if (0 === sizeof($anniversaryUserDTOs) || empty($text)) {
            return [];
        }

        return [
            ['type' => 'divider'],
            ['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => "🎉 | *Work Anniversaries*\n\n".$text]],
        ];
    }

    /**
     * @return array{int, array{string, string[]}}
     */
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

    /**
     * @return array{int, array{string, string[]}}
     */
    private function generateEmptyDigestMessage(): array
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

    /**
     * @param array{int, array{string, string[]}} $contextSection
     */
    private function handleNoLeaveAndBirthdaysDigest(array $contextSection): void
    {
        $options = new SlackOptions([
            'channel' => $this->dailyDigestChannelId,
            'blocks' => [
                ...$contextSection,
                ...$this->generateEmptyDigestMessage(),
            ],
        ]);

        $this->chatter->send(new ChatMessage(self::SUBJECT_MESSAGE)->options($options));
    }
}
