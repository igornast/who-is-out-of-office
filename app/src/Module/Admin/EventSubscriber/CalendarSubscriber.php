<?php

declare(strict_types=1);

namespace App\Module\Admin\EventSubscriber;

use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\LeaveRequestTypeEnum;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use CalendarBundle\Event\SetDataEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use CalendarBundle\Entity\Event;

class CalendarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly UserFacadeInterface $userFacade,
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

        $this->addLeaveRequestEvents($event, $start, $end);
        $this->addBirthdayEvents($event, $start);
    }

    private function addLeaveRequestEvents(SetDataEvent $event, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $statuses = [
            LeaveRequestStatusEnum::Pending,
            LeaveRequestStatusEnum::Approved,
        ];

        $leaveRequestDTOs = $this->leaveRequestFacade->getLeaveRequestsForDates($start, $end, $statuses);

        foreach ($leaveRequestDTOs as $dto) {
            $title = sprintf('%s %s %s', $this->getLeaveIcon($dto->leaveType), $dto->user->firstName, $dto->user->lastName);
            $style = $this->getLeaveEventStyle($dto->status, $dto->leaveType);

            $calendarEvent = new Event(
                $title,
                \DateTime::createFromImmutable($dto->startDate),
                \DateTime::createFromImmutable($dto->endDate)
            );

            $calendarEvent->setAllDay(true);
            $calendarEvent->setOptions([
                'backgroundColor' => $style['backgroundColor'],
                'borderColor' => $style['borderColor'],
                'textColor' => '#000000',
                'extendedProps' => [
                    'status' => $dto->status->value,
                ],
            ]);

            $calendarEvent->addOption('url', '/admin/leave-request/'.$dto->id);
            $event->addEvent($calendarEvent);
        }
    }

    /**
     * @return array{'backgroundColor': string, 'borderColor': string}
     */
    private function getLeaveEventStyle(LeaveRequestStatusEnum $status, LeaveRequestTypeEnum $type): array
    {
        return match (true) {
            LeaveRequestStatusEnum::Pending === $status => [
                'backgroundColor' => '#fff3cd',
                'borderColor' => '#ffeeba',
            ],
            LeaveRequestStatusEnum::Approved === $status && LeaveRequestTypeEnum::Vacation === $type => [
                'backgroundColor' => '#d4edda',
                'borderColor' => '#28a745',
            ],
            LeaveRequestStatusEnum::Approved === $status && LeaveRequestTypeEnum::SickLeave === $type => [
                'backgroundColor' => '#fce4ec',
                'borderColor' => '#f06292',
            ],
            default => [
                'backgroundColor' => '#e2e3e5',
                'borderColor' => '#d6d8db',
            ],
        };
    }

    private function getLeaveIcon(LeaveRequestTypeEnum $type): string
    {
        return match ($type) {
            LeaveRequestTypeEnum::SickLeave => '🤒',
            LeaveRequestTypeEnum::Vacation => '🌴',
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

            $birthdayThisYear = \DateTime::createFromFormat('Y-m-d', $start->format('Y').'-'.$birthday->format('m-d'));

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
                ],
            ]);

            $event->addEvent($calendarEvent);
        }
    }
}
