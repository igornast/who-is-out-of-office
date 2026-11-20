<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\SlackHolidayStatus;
use App\Infrastructure\Doctrine\Entity\User;
use App\Infrastructure\Slack\DTO\SlackHolidayStatusCandidateDTO;
use App\Infrastructure\Slack\DTO\SlackHolidayStatusDTO;
use App\Infrastructure\Slack\Repository\SlackHolidayStatusRepositoryInterface;
use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<SlackHolidayStatus>
 */
class SlackHolidayStatusRepository extends ServiceEntityRepository implements SlackHolidayStatusRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SlackHolidayStatus::class);
    }

    public function findCandidatesForDay(\DateTimeImmutable $day): array
    {
        $sql = <<<'SQL'
            SELECT h.id, h.date, h.description, h.is_global, h.counties, hc.country_code, u.id AS user_id
            FROM holiday h
            INNER JOIN holiday_calendar hc ON hc.id = h.holiday_calendar_id AND hc.is_active = 1
            INNER JOIN user u ON u.holiday_calendar_id = hc.id AND u.is_active = 1
            INNER JOIN user_slack_integration si ON si.user_id = u.id AND si.slack_member_id IS NOT NULL AND si.slack_status_sync_enabled = 1
            WHERE h.date = :day
              AND (h.is_global = 1 OR u.subdivision_code IS NULL OR JSON_CONTAINS(h.counties, JSON_QUOTE(u.subdivision_code)))
              AND NOT EXISTS (
                SELECT 1 FROM leave_request lr
                WHERE lr.user_id = u.id AND lr.status = :approved AND lr.is_external_status_synced = 1
                  AND lr.start_date <= :dayStart AND lr.end_date >= :dayStart
              )
            ORDER BY h.id
        SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'day' => $day->format('Y-m-d'),
            'dayStart' => $day->format('Y-m-d 00:00:00'),
            'approved' => LeaveRequestStatusEnum::Approved->value,
        ])->fetchAllAssociative();

        if ([] === $rows) {
            return [];
        }

        $users = $this->loadUsers(array_values(array_unique(array_map(fn (array $row): string => (string) $row['user_id'], $rows))));
        $grouped = [];
        foreach ($rows as $row) {
            $userId = (string) $row['user_id'];
            $grouped[$userId]['countryCode'] = (string) $row['country_code'];
            $grouped[$userId]['holidays'][] = PublicHolidayDTO::fromArray($row);
        }

        $result = [];
        foreach ($grouped as $userId => $data) {
            if (!isset($users[$userId])) {
                continue;
            }

            $result[] = new SlackHolidayStatusCandidateDTO(
                countryCode: $data['countryCode'],
                holidays: $data['holidays'],
                user: UserDTO::fromEntity($users[$userId]),
            );
        }

        return $result;
    }

    public function findAllTracked(\DateTimeImmutable $day): array
    {
        $sql = <<<'SQL'
            SELECT shs.id, shs.user_id, shs.status_text, shs.date, shs.holiday_id,
                   EXISTS (
                       SELECT 1 FROM leave_request lr
                       WHERE lr.user_id = shs.user_id AND lr.status = :approved AND lr.is_external_status_synced = 1
                         AND lr.start_date <= :dayStart AND lr.end_date >= :dayStart
                   ) AS is_user_on_synced_leave
            FROM slack_holiday_status shs
            ORDER BY shs.date ASC, shs.id ASC
        SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'dayStart' => $day->format('Y-m-d 00:00:00'),
            'approved' => LeaveRequestStatusEnum::Approved->value,
        ])->fetchAllAssociative();

        if ([] === $rows) {
            return [];
        }

        $users = $this->loadUsers(array_values(array_unique(array_map(fn (array $row): string => (string) $row['user_id'], $rows))));

        $rows = array_values(array_filter($rows, fn (array $row): bool => isset($users[(string) $row['user_id']])));

        return array_map(function (array $row) use ($users): SlackHolidayStatusDTO {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $row['date']);
            if (false === $date) {
                throw new \RuntimeException('Invalid tracked holiday status date');
            }

            return new SlackHolidayStatusDTO(
                id: (string) $row['id'],
                statusText: (string) $row['status_text'],
                isUserOnSyncedLeave: (bool) $row['is_user_on_synced_leave'],
                user: UserDTO::fromEntity($users[(string) $row['user_id']]),
                date: $date,
                holidayId: null !== $row['holiday_id'] ? (string) $row['holiday_id'] : null,
            );
        }, $rows);
    }

    public function upsert(string $userId, string $holidayId, \DateTimeImmutable $date, string $statusText): void
    {
        $sql = <<<'SQL'
            INSERT INTO slack_holiday_status (id, user_id, holiday_id, date, status_text, created_at, updated_at)
            VALUES (:id, :userId, (SELECT h.id FROM holiday h WHERE h.id = :holidayId), :date, :statusText, :now, :now) AS new
            ON DUPLICATE KEY UPDATE holiday_id = new.holiday_id, status_text = new.status_text, updated_at = new.updated_at
        SQL;

        $now = new \DateTimeImmutable();
        $this->getEntityManager()->getConnection()->executeStatement($sql, [
            'id' => Uuid::uuid4()->toString(),
            'userId' => $userId,
            'holidayId' => $holidayId,
            'date' => $date->format('Y-m-d'),
            'statusText' => $statusText,
            'now' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM slack_holiday_status WHERE id = :id',
            ['id' => $id],
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, User>
     */
    private function loadUsers(array $ids): array
    {
        $items = $this->getEntityManager()->createQueryBuilder()
            ->select('u, si, hc')
            ->from(User::class, 'u')
            ->leftJoin('u.slackIntegration', 'si')
            ->leftJoin('u.holidayCalendar', 'hc')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $users = [];
        foreach ($items as $user) {
            $users[$user->id->toString()] = $user;
        }

        return $users;
    }
}
