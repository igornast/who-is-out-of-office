<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\Repository;

use App\Infrastructure\Slack\DTO\SlackHolidayStatusCandidateDTO;
use App\Infrastructure\Slack\DTO\SlackHolidayStatusDTO;

interface SlackHolidayStatusRepositoryInterface
{
    /**
     * @return list<SlackHolidayStatusCandidateDTO>
     */
    public function findCandidatesForDay(\DateTimeImmutable $day): array;

    /**
     * @return list<SlackHolidayStatusDTO>
     */
    public function findAllTracked(\DateTimeImmutable $day): array;

    public function upsert(string $userId, string $holidayId, \DateTimeImmutable $date, string $statusText): void;

    public function delete(string $id): void;
}
