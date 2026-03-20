<?php

declare(strict_types=1);

namespace App\Module\Admin\EventSubscriber;

use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use CalendarBundle\Event\SetDataEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use CalendarBundle\Entity\Event;

class CalendarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly UserFacadeInterface $userFacade,
        private readonly HolidayFacadeInterface $holidayFacade,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SetDataEvent::class => 'onCalendarSetData',
        ];
    }

    public function onCalendarSetData(SetDataEvent $event): void
    {
        $start = \DateTimeImmutable::createFromInterface($event->getStart());
        $end = \DateTimeImmutable::createFromInterface($event->getEnd());
        $filters = $this->parseFilters($event->getFilters());

        /** @var User $user */
        $user = $this->security->getUser();
        $currentUserDTO = UserDTO::fromEntity($user);

        $this->markNonWorkingDaysForUser($event, $currentUserDTO);
        $this->addPublicHolidayEvents($event, $currentUserDTO);
        $this->addLeaveRequestEvents($event, $start, $end, $filters);
        $this->addBirthdayEvents($event, $start);
    }

    /**
     * @param mixed[] $rawFilters
     *
     * @return array{leaveTypeId: ?string, status: ?string}
     */
    private function parseFilters(array $rawFilters): array
    {
        return [
            'leaveTypeId' => isset($rawFilters['leaveTypeId']) && '' !== $rawFilters['leaveTypeId']
                ? (string) $rawFilters['leaveTypeId']
                : null,
            'status' => isset($rawFilters['status']) && '' !== $rawFilters['status']
                ? (string) $rawFilters['status']
                : null,
        ];
    }

    /**
     * @param array{leaveTypeId: ?string, status: ?string} $filters
     */
    private function addLeaveRequestEvents(SetDataEvent $event, \DateTimeImmutable $start, \DateTimeImmutable $end, array $filters): void
    {
        $statuses = match ($filters['status']) {
            'pending' => [LeaveRequestStatusEnum::Pending],
            'approved' => [LeaveRequestStatusEnum::Approved],
            default => [LeaveRequestStatusEnum::Pending, LeaveRequestStatusEnum::Approved],
        };

        $leaveRequestDTOs = $this->leaveRequestFacade->getLeaveRequestsForDates($start, $end, $statuses);

        foreach ($leaveRequestDTOs as $dto) {
            if (null !== $filters['leaveTypeId'] && $dto->leaveType->id->toString() !== $filters['leaveTypeId']) {
                continue;
            }
            $leaveTypeDTO = $dto->leaveType;

            $title = sprintf('%s %s %s', $leaveTypeDTO->icon, $dto->user->firstName, $dto->user->lastName);
            $calendarEvent = new Event(
                $title,
                \DateTime::createFromImmutable($dto->startDate),
                \DateTime::createFromImmutable($dto->endDate->modify('+1 day')),
            );
            $style = $this->getLeaveEventStyle($dto->status, $leaveTypeDTO);

            $calendarEvent->setOptions([
                'backgroundColor' => $style['backgroundColor'],
                'borderColor' => $style['borderColor'],
                'textColor' => $style['textColor'],
                'allDay' => true,
                'extendedProps' => [
                    'type' => 'leave',
                    'status' => $dto->status->value,
                    'leaveTypeName' => $leaveTypeDTO->name,
                    'leaveTypeIcon' => $leaveTypeDTO->icon,
                    'employeeName' => sprintf('%s %s', $dto->user->firstName, $dto->user->lastName),
                    'workDays' => $dto->workDays,
                    'comment' => $dto->comment,
                    'startDate' => $dto->startDate->format('M j, Y'),
                    'endDate' => $dto->endDate->format('M j, Y'),
                    'detailUrl' => $this->urlGenerator->generate('app_dashboard_app_leave_request_detail', [
                        'entityId' => $dto->id->toString(),
                    ]),
                ],
            ]);
            $event->addEvent($calendarEvent);
        }
    }

    /**
     * @return array{'backgroundColor': string, 'borderColor': string, 'textColor': string}
     */
    private function getLeaveEventStyle(LeaveRequestStatusEnum $status, LeaveRequestTypeDTO $type): array
    {
        return match (true) {
            LeaveRequestStatusEnum::Pending === $status => [
                'backgroundColor' => '#fff3cd',
                'borderColor' => '#ffeeba',
                'textColor' => '#000000',
            ],
            default => [
                'backgroundColor' => $type->backgroundColor,
                'borderColor' => $type->borderColor,
                'textColor' => $type->textColor,
            ],
        };
    }

    private function addBirthdayEvents(SetDataEvent $event, \DateTimeImmutable $start): void
    {
        $userDTOs = $this->userFacade->getUsersWithBirthdaysForDates($start, \DateTimeImmutable::createFromMutable($event->getEnd()));

        foreach ($userDTOs as $userDTO) {
            $birthday = $userDTO->birthDate;

            if (null === $birthday) {
                continue;
            }

            $birthdayThisYear = \DateTime::createFromFormat('Y-m-d', sprintf('%s-%s', $start->format('Y'), $birthday->format('m-d')));

            if (false === $birthdayThisYear) {
                continue;
            }

            $calendarEvent = new Event(
                sprintf('🥳 %s %s', $userDTO->firstName, $userDTO->lastName),
                $birthdayThisYear,
                $birthdayThisYear
            );

            $calendarEvent->setOptions([
                'backgroundColor' => '#e0f7fa',
                'borderColor' => '#00acc1',
                'textColor' => '#000000',
                'className' => ['birthday-event'],
                'allDay' => true,
                'extendedProps' => [
                    'type' => 'birthday',
                    'userId' => $userDTO->id,
                    'employeeName' => sprintf('%s %s', $userDTO->firstName, $userDTO->lastName),
                    'date' => $birthdayThisYear->format('M j'),
                ],
            ]);

            $event->addEvent($calendarEvent);
        }
    }

    private function markNonWorkingDaysForUser(SetDataEvent $event, UserDTO $currentUserDTO): void
    {
        $nonWorkingDays = array_filter(
            range(1, 5),
            fn ($day) => !in_array($day, $currentUserDTO->workingDays)
        );

        $start = $event->getStart();
        $end = $event->getEnd();
        $period = new \DatePeriod($start, new \DateInterval('P1D'), $end);

        foreach ($period as $date) {
            $weekday = (int) $date->format('N');

            if (in_array($weekday, $nonWorkingDays)) {
                $calendarEvent = new Event('⛔ Off Day', $date);
                $calendarEvent->setOptions([
                    'backgroundColor' => '#f0f0f0',
                    'borderColor' => '#d3d3d3',
                    'textColor' => '#888',
                    'display' => 'background',
                    'allDay' => true,
                ]);
                $event->addEvent($calendarEvent);
            }
        }
    }

    private function addPublicHolidayEvents(SetDataEvent $event, UserDTO $currentUserDTO): void
    {
        if (null === $currentUserDTO->calendarCountryCode) {
            return;
        }

        $holidayCalendarDTO = $this->holidayFacade->getHolidayCalendarForCountry($currentUserDTO->calendarCountryCode);

        foreach ($holidayCalendarDTO->holidays as $holidayDTO) {
            $calendarEvent = new Event(
                sprintf('🎌 %s', $holidayDTO->description),
                \DateTime::createFromImmutable($holidayDTO->date),
            );
            $calendarEvent->setOptions([
                'backgroundColor' => '#fde2e2',
                'borderColor' => '#f5bcbc',
                'textColor' => '#b30000',
                'allDay' => true,
                'extendedProps' => [
                    'type' => 'holiday',
                    'description' => $holidayDTO->description,
                    'date' => $holidayDTO->date->format('M j, Y'),
                    'isGlobal' => $holidayDTO->isGlobal,
                    'counties' => $this->translateCounties($holidayDTO->counties),
                ],
            ]);

            $event->addEvent($calendarEvent);
        }
    }

    /**
     * @param string[]|null $counties
     *
     * @return string[]
     */
    private function translateCounties(?array $counties): array
    {
        if (null === $counties || [] === $counties) {
            return [];
        }

        return array_map(
            function (string $code): string {
                $key = sprintf('subdivision.%s', $code);
                $translated = $this->translator->trans($key, [], 'admin');

                return $translated === $key ? $code : $translated;
            },
            $counties
        );
    }
}
