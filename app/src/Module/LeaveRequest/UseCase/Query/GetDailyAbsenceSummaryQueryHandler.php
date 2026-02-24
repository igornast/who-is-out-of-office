<?php

declare(strict_types=1);

namespace App\Module\LeaveRequest\UseCase\Query;

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\Dashboard\DailyAbsenceSummaryDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;

class GetDailyAbsenceSummaryQueryHandler
{
    public function __construct(
        private readonly LeaveRequestRepositoryInterface $repository,
    ) {
    }

    /**
     * @return DailyAbsenceSummaryDTO[]
     */
    public function handle(?\DateTimeImmutable $weekStart = null): array
    {
        $today = new \DateTimeImmutable('today');
        $monday = $weekStart ?? new \DateTimeImmutable('monday this week');
        $friday = $monday->modify('+4 days');

        $leaveRequests = $this->repository->findForDates(
            $monday,
            $friday,
            [LeaveRequestStatusEnum::Approved],
        );

        $days = [];
        for ($i = 0; $i < 5; ++$i) {
            $date = $monday->modify(sprintf('+%d days', $i));
            $dateStr = $date->format('Y-m-d');

            $seen = [];
            $avatars = [];
            foreach ($leaveRequests as $request) {
                if ($request->startDate->format('Y-m-d') <= $dateStr
                    && $request->endDate->format('Y-m-d') >= $dateStr
                    && !isset($seen[$request->user->id])
                ) {
                    $seen[$request->user->id] = true;
                    $avatars[] = [
                        'firstName' => $request->user->firstName,
                        'lastName' => $request->user->lastName,
                        'profileImageUrl' => $request->user->profileImageUrl,
                        'leaveTypeBackgroundColor' => $request->leaveType->backgroundColor,
                    ];
                }
            }

            $days[] = new DailyAbsenceSummaryDTO(
                date: $date,
                dayName: $date->format('D'),
                dayNumber: (int) $date->format('j'),
                isToday: $dateStr === $today->format('Y-m-d'),
                absenceCount: count($avatars),
                avatars: $avatars,
            );
        }

        return $days;
    }
}
