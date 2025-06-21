<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\Service\EmojisProvider;
use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
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
        private readonly HolidayFacadeInterface $holidayFacade,
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
    ) {
    }

    public function handle(): void
    {
        $today   = new \DateTimeImmutable()->setTime(0, 0, 0);
        $weekDay = (int) $today->format('N');
        $monday  = $today->modify('-'.($weekDay - 1).' days');
        $sunday  = $monday->modify('+6 days');

        /** @var array{string, LeaveRequestDTO[]} $mapUserIdToApprovedRequests */
        $mapUserIdToApprovedRequests = $this->leaveRequestFacade->getLeaveRequestsForDatesGroupedByUserId($monday, $sunday, [LeaveRequestStatusEnum::Approved]);
        /** @var array{string, UserPublicHolidaysDTO} $mapUserIdToHolidays */
        $mapUserIdToHolidays = $this->holidayFacade->getHolidaysForDatesGroupedByUserId($monday, $sunday);

        /** @var array{string, array{int, LeaveRequestDTO|UserPublicHolidaysDTO}} $mergedEvents */
        $mergedEvents = collect($mapUserIdToApprovedRequests)
        ->union($mapUserIdToHolidays)
            ->map(function ($item, $key) use ($mapUserIdToApprovedRequests, $mapUserIdToHolidays) {
                $publicHolidays = $mapUserIdToHolidays[$key] ?? null;

                if (!$publicHolidays instanceof UserPublicHolidaysDTO) {
                    return $mapUserIdToApprovedRequests[$key];
                }

                return array_merge($mapUserIdToApprovedRequests[$key] ?? [], [$publicHolidays]);
            })
            ->all();

        $birthdayUsers = $this->userFacade->getUsersWithBirthdaysForDates($monday, $sunday);

        $oooSection = $this->generateWhoIsOutSection($mergedEvents);
        $birthdaysSection = $this->generateBirthdaysSection($birthdayUsers);
        $contextSection = $this->generateHeaderSection();

        if (0 === sizeof($oooSection) && 0 === sizeof($birthdaysSection)) {
            $this->handleNoLeaveAndBirthdaysDigest($contextSection);

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

        $this->chatter->send(new ChatMessage(self::SUBJECT_MESSAGE)->options($options));
    }

    /**
     * @param array{string, array{int, LeaveRequestDTO|UserPublicHolidaysDTO}} $events
     */
    private function generateWhoIsOutSection(array $events): array
    {
        if (0 === sizeof($events)) {
            return [];
        }

        $text = '';
        /** @var array{int, LeaveRequestDTO|UserPublicHolidaysDTO} $userEvents */
        foreach ($events as $userEvents) {

            $firstAbsenseDTO = $userEvents[0];

            if (!$firstAbsenseDTO instanceof LeaveRequestDTO && !$firstAbsenseDTO instanceof UserPublicHolidaysDTO) {
                continue;
            }

            $user = $firstAbsenseDTO->user;
            $text .= sprintf(
                "*%s %s*\n",
                $user->firstName,
                $user->lastName,
            );

            /** @var LeaveRequestDTO|UserPublicHolidaysDTO $event */
            foreach ($userEvents as $event) {

                if ($event instanceof LeaveRequestDTO) {
                    $text .= sprintf(
                        "    ‣ %s %s _(%s - %s)_\n",
                        EmojisProvider::getLeaveTypeEmoji($event->leaveType),
                        $event->leaveType->name,
                        $event->startDate->format('F d'),
                        $event->endDate->format('F d')
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
     */
    private function generateBirthdaysSection(array $birthdayUserDTOs): array
    {

        if (0 === sizeof($birthdayUserDTOs)) {
            return [];
        }

        $text = '';
        foreach ($birthdayUserDTOs as $userDTO) {
            $text .=  sprintf(
                "    ‣ *%s %s* - %s\n",
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
