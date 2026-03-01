<?php

declare(strict_types=1);

namespace App\Module\Admin\EventSubscriber;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Controller\LeaveRequest\LeaveRequestCrudController;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\HolidayFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Facade\UserFacadeInterface;
use CalendarBundle\Event\SetDataEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use CalendarBundle\Entity\Event;

class CalendarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly UserFacadeInterface $userFacade,
        private readonly HolidayFacadeInterface $holidayFacade,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly Security $security,
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

        $this->markNonWorkingDaysForUser($event);
        $this->addPublicHolidayEvents($event);
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
                    'detailUrl' => $this->adminUrlGenerator
                        ->setController(LeaveRequestCrudController::class)
                        ->setAction(Action::DETAIL)
                        ->setEntityId($dto->id->toString())
                        ->generateUrl(),
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

            /** @var \DateTime $birthdayThisYear */
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
                    'employeeName' => sprintf('%s %s', $userDTO->firstName, $userDTO->lastName),
                    'date' => $birthdayThisYear->format('M j'),
                ],
            ]);

            $event->addEvent($calendarEvent);
        }
    }

    private function markNonWorkingDaysForUser(SetDataEvent $event): void
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $userDTO = UserDTO::fromEntity($user);

        $nonWorkingDays = array_filter(
            range(1, 5),
            fn ($day) => !in_array($day, $userDTO->workingDays)
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

    private function addPublicHolidayEvents(SetDataEvent $event): void
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $userDTO = UserDTO::fromEntity($user);

        if (null === $userDTO->calendarCountryCode) {
            return;
        }

        $holidayCalendarDTO = $this->holidayFacade->getHolidayCalendarForCountry($userDTO->calendarCountryCode);

        foreach ($holidayCalendarDTO->holidays as $holidayDTO) {
            $calendarEvent = new Event(
                '🎌 '.$holidayDTO->description,
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
                ],
            ]);

            $event->addEvent($calendarEvent);
        }
    }
}
