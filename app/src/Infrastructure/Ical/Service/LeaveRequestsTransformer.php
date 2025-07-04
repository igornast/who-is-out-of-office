<?php

declare(strict_types=1);

namespace App\Infrastructure\Ical\Service;

use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Service\Messaging\EmojisProvider;
use App\Shared\Service\UserMessagingTranslator;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\MultiDay;
use Eluceo\iCal\Presentation\Component;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;

class LeaveRequestsTransformer
{
    public function __construct(private readonly UserMessagingTranslator $translator)
    {
    }

    /**
     * @param LeaveRequestDTO[] $leaveRequestDTOs
     */
    public function transformToCalendar(array $leaveRequestDTOs): Component
    {

        $events = [];
        foreach ($leaveRequestDTOs as $leaveRequestDTO) {
            $summary = sprintf(
                '%s %s %s',
                EmojisProvider::getLeaveTypeEmoji($leaveRequestDTO->leaveType),
                $leaveRequestDTO->user->firstName,
                $leaveRequestDTO->user->lastName
            );

            $description = sprintf(
                '%s (%s - %s) %s',
                $this->translator->translate('leave_request.type.'.$leaveRequestDTO->leaveType->value),
                $leaveRequestDTO->startDate->format('F d'),
                $leaveRequestDTO->endDate->format('F d'),
                $leaveRequestDTO->comment
            );

            $events[] = new Event()
                ->setSummary($summary)
                ->setDescription($description)
                ->setOccurrence(
                    new MultiDay(
                        new Date($leaveRequestDTO->startDate),
                        new Date($leaveRequestDTO->endDate),
                    )
                );
        }

        return new CalendarFactory()->createCalendar(new Calendar($events));
    }
}
