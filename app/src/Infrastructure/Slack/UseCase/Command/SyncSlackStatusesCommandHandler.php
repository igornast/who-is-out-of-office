<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\UseCase\Command;

use App\Infrastructure\Slack\DTO\SlackHolidayStatusCandidateDTO;
use App\Infrastructure\Slack\DTO\SlackHolidayStatusDTO;
use App\Infrastructure\Slack\Repository\SlackHolidayStatusRepositoryInterface;
use App\Infrastructure\Slack\Service\HolidayStatusTextBuilder;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Shared\Facade\AppSettingsFacadeInterface;
use App\Shared\Facade\LeaveRequestFacadeInterface;
use App\Shared\Service\Messaging\EmojisProvider;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

class SyncSlackStatusesCommandHandler
{
    public function __construct(
        private readonly LeaveRequestFacadeInterface $leaveRequestFacade,
        private readonly AppSettingsFacadeInterface $appSettingsFacade,
        private readonly SetSlackStatusCommandHandler $setHandler,
        private readonly ClearSlackStatusCommandHandler $clearHandler,
        private readonly LoggerInterface $logger,
        private readonly SlackHolidayStatusRepositoryInterface $holidayStatusRepository,
        private readonly HolidayStatusTextBuilder $holidayStatusTextBuilder,
        private readonly ClockInterface $clock,
    ) {
    }

    public function handle(): void
    {
        if (!$this->appSettingsFacade->isSlackStatusSyncEnabled()) {
            return;
        }

        $clearedLeaveUserIds = $this->clearLeaveStatuses();
        $this->setLeaveStatuses();
        $this->syncHolidayStatuses($clearedLeaveUserIds);
    }

    /**
     * @return list<string>
     */
    private function clearLeaveStatuses(): array
    {
        $clearedUserIds = [];

        foreach ($this->leaveRequestFacade->findSyncedNeedingClear() as $leaveRequest) {
            try {
                $success = $this->clearHandler->handle($leaveRequest->user);
                if ($success) {
                    $clearedUserIds[$leaveRequest->user->id] = true;
                    $this->leaveRequestFacade->markExternalStatusSynced($leaveRequest->id->toString(), false);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to clear slack status', [
                    'leave_request_id' => $leaveRequest->id->toString(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return array_keys($clearedUserIds);
    }

    private function setLeaveStatuses(): void
    {
        foreach ($this->leaveRequestFacade->findApprovedActiveNotSynced() as $leaveRequest) {
            try {
                $text = sprintf('%s until %s', $leaveRequest->leaveType->name, $leaveRequest->endDate->format('M j'));
                $emoji = $leaveRequest->leaveType->slackStatusEmoji ?? LeaveRequestTypeDTO::DEFAULT_SLACK_STATUS_EMOJI;
                $success = $this->setHandler->handle($leaveRequest->user, $text, $emoji, $leaveRequest->endDate);
                if ($success) {
                    $this->leaveRequestFacade->markExternalStatusSynced($leaveRequest->id->toString(), true);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to set slack status', [
                    'leave_request_id' => $leaveRequest->id->toString(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param list<string> $clearedLeaveUserIds
     */
    private function syncHolidayStatuses(array $clearedLeaveUserIds): void
    {
        $today = $this->clock->now()->setTime(0, 0);

        try {
            $candidates = [];
            foreach ($this->holidayStatusRepository->findCandidatesForDay($today) as $candidate) {
                if (in_array((int) $today->format('N'), $candidate->user->workingDays)) {
                    $candidates[$candidate->user->id] = $candidate;
                }
            }
            $tracked = $this->holidayStatusRepository->findAllTracked($today);
        } catch (\Throwable $e) {
            $this->logger->error('[SLACK][HOLIDAY-STATUS]: Failed to load holiday statuses', [
                'exception' => $e->getMessage(),
            ]);

            return;
        }

        $this->clearHolidayStatuses($today, $candidates, $tracked);
        $this->setHolidayStatuses($today, $candidates, $tracked, $clearedLeaveUserIds);
    }

    /**
     * @param array<string, SlackHolidayStatusCandidateDTO> $candidates
     * @param list<SlackHolidayStatusDTO>                   $tracked
     */
    private function clearHolidayStatuses(
        \DateTimeImmutable $today,
        array $candidates,
        array $tracked,
    ): void {
        $todayString = $today->format('Y-m-d');

        foreach ($tracked as $status) {
            try {
                if ($status->date->format('Y-m-d') !== $todayString) {
                    $this->holidayStatusRepository->delete($status->id);

                    continue;
                }

                if (isset($candidates[$status->user->id])) {
                    continue;
                }

                if ($status->isUserOnSyncedLeave || !$status->user->slackStatusSyncEnabled || null === $status->user->slackMemberId) {
                    $this->holidayStatusRepository->delete($status->id);

                    continue;
                }

                if ($this->clearHandler->handle($status->user)) {
                    $this->holidayStatusRepository->delete($status->id);
                }
            } catch (\Throwable $e) {
                $this->logger->error('[SLACK][HOLIDAY-STATUS]: Failed to clear slack holiday status', [
                    'user_id' => $status->user->id,
                    'holiday_id' => $status->holidayId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param array<string, SlackHolidayStatusCandidateDTO> $candidates
     * @param list<SlackHolidayStatusDTO>                   $tracked
     * @param list<string>                                  $clearedLeaveUserIds
     */
    private function setHolidayStatuses(
        \DateTimeImmutable $today,
        array $candidates,
        array $tracked,
        array $clearedLeaveUserIds,
    ): void {
        $todayString = $today->format('Y-m-d');
        $trackedByUserId = [];
        foreach ($tracked as $status) {
            if ($status->date->format('Y-m-d') === $todayString) {
                $trackedByUserId[$status->user->id] = $status;
            }
        }

        foreach ($candidates as $candidate) {
            $holidayId = null;
            try {
                $sortedHolidays = $this->holidayStatusTextBuilder->sortForStatus($candidate->holidays);
                $statusText = $this->holidayStatusTextBuilder->buildText($sortedHolidays);
                $holidayId = $sortedHolidays[0]->id;
                $trackedStatus = in_array($candidate->user->id, $clearedLeaveUserIds, true)
                    ? null
                    : ($trackedByUserId[$candidate->user->id] ?? null);

                if (null !== $trackedStatus && $trackedStatus->statusText === $statusText) {
                    if ($trackedStatus->holidayId !== $holidayId) {
                        $this->holidayStatusRepository->upsert($candidate->user->id, $holidayId, $today, $statusText);
                    }

                    continue;
                }

                $emoji = EmojisProvider::getFlagShortcode($candidate->countryCode);
                $fallback = EmojisProvider::GENERIC_HOLIDAY_SHORTCODE === $emoji
                    ? null
                    : EmojisProvider::GENERIC_HOLIDAY_SHORTCODE;
                if ($this->setHandler->handle($candidate->user, $statusText, $emoji, $today, $fallback)) {
                    $this->holidayStatusRepository->upsert($candidate->user->id, $holidayId, $today, $statusText);
                }
            } catch (\Throwable $e) {
                $this->logger->error('[SLACK][HOLIDAY-STATUS]: Failed to set slack holiday status', [
                    'user_id' => $candidate->user->id,
                    'holiday_id' => $holidayId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
