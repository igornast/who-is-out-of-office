<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\LeavePeriod;
use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\User\Repository\BalanceLedgerEntryRepositoryInterface;
use App\Shared\DTO\Balance\BalanceLedgerEntryDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<BalanceLedgerEntry>
 */
class BalanceLedgerEntryRepository extends ServiceEntityRepository implements BalanceLedgerEntryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BalanceLedgerEntry::class);
    }

    public function add(BalanceLedgerEntryDTO $entryDTO): void
    {
        $em = $this->getEntityManager();
        /** @var User $user */
        $user = $em->getReference(User::class, $entryDTO->userId);
        /** @var LeavePeriod $leavePeriod */
        $leavePeriod = $em->getReference(LeavePeriod::class, $entryDTO->leavePeriodId);

        $entry = new BalanceLedgerEntry(
            id: Uuid::fromString($entryDTO->id),
            delta: $entryDTO->delta,
            balanceBefore: $entryDTO->balanceBefore,
            balanceAfter: $entryDTO->balanceAfter,
            sequence: $this->nextSequence($entryDTO->userId),
            type: $entryDTO->type,
            source: $entryDTO->source,
            user: $user,
            leavePeriod: $leavePeriod,
            reason: $entryDTO->reason,
            actor: null !== $entryDTO->actorUserId ? $em->getReference(User::class, $entryDTO->actorUserId) : null,
            leaveRequest: null !== $entryDTO->leaveRequestId ? $em->getReference(LeaveRequest::class, $entryDTO->leaveRequestId) : null,
        );
        $entry->setCreatedAt($entryDTO->createdAt);
        $entry->setUpdatedAt($entryDTO->createdAt);

        $em->persist($entry);
        $em->flush();
    }

    public function hasUsageForLeaveRequest(string $leaveRequestId): bool
    {
        return 0 < (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM balance_ledger_entry WHERE leave_request_id = :leaveRequestId AND type = :type',
            ['leaveRequestId' => $leaveRequestId, 'type' => BalanceLedgerEntryTypeEnum::Usage->value],
        );
    }

    public function hasEntryForLeaveRequest(string $leaveRequestId): bool
    {
        return 0 < (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM balance_ledger_entry WHERE leave_request_id = :leaveRequestId',
            ['leaveRequestId' => $leaveRequestId],
        );
    }

    public function hasMigrationOpeningEntryAfter(string $userId, \DateTimeImmutable $createdAt): bool
    {
        return 0 < (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM balance_ledger_entry WHERE user_id = :userId AND type = :type AND source = :source AND created_at > :createdAt',
            [
                'userId' => $userId,
                'type' => BalanceLedgerEntryTypeEnum::OpeningBalance->value,
                'source' => BalanceLedgerSourceEnum::Migration->value,
                'createdAt' => $createdAt->format('Y-m-d H:i:s'),
            ],
        );
    }

    /**
     * @return BalanceLedgerEntryDTO[]
     */
    public function findForUser(string $userId, ?string $leavePeriodId, int $limit, int $offset): array
    {
        $periodFilter = null !== $leavePeriodId ? 'AND ble.leave_period_id = :leavePeriodId' : '';

        $sql = sprintf(<<<'SQL'
            SELECT ble.id, ble.user_id, ble.leave_period_id, ble.type, ble.source, ble.delta,
                   ble.balance_before, ble.balance_after, ble.reason, ble.actor_user_id,
                   ble.leave_request_id, ble.created_at,
                   actor.first_name AS actor_first_name, actor.last_name AS actor_last_name,
                   lrt.name AS leave_type_name,
                   lr.start_date AS leave_request_start_date,
                   lr.end_date AS leave_request_end_date
            FROM balance_ledger_entry ble
            LEFT JOIN user actor ON actor.id = ble.actor_user_id
            LEFT JOIN leave_request lr ON lr.id = ble.leave_request_id
            LEFT JOIN leave_request_type lrt ON lrt.id = lr.leave_type
            WHERE ble.user_id = :userId
              %s
            ORDER BY ble.sequence DESC
            LIMIT :limit OFFSET :offset
        SQL, $periodFilter);

        $parameters = ['userId' => $userId, 'limit' => $limit, 'offset' => $offset];
        $types = [
            'userId' => ParameterType::STRING,
            'limit' => ParameterType::INTEGER,
            'offset' => ParameterType::INTEGER,
        ];

        if (null !== $leavePeriodId) {
            $parameters['leavePeriodId'] = $leavePeriodId;
            $types['leavePeriodId'] = ParameterType::STRING;
        }

        $rows = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, $parameters, $types)
            ->fetchAllAssociative();

        return array_map(
            fn (array $row) => BalanceLedgerEntryDTO::fromArray(array_filter($row, fn ($value) => null !== $value)),
            $rows,
        );
    }

    /**
     * @return array<string, int>
     */
    public function sumDeltasByTypeForPeriod(string $leavePeriodId): array
    {
        $sql = <<<'SQL'
            SELECT type, SUM(delta) AS total
            FROM balance_ledger_entry
            WHERE leave_period_id = :leavePeriodId
            GROUP BY type
        SQL;

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'leavePeriodId' => $leavePeriodId,
        ])->fetchAllAssociative();

        $totals = [];
        foreach ($rows as $row) {
            $totals[(string) $row['type']] = (int) $row['total'];
        }

        return $totals;
    }

    private function nextSequence(string $userId): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COALESCE(MAX(sequence), 0) + 1 FROM balance_ledger_entry WHERE user_id = :userId',
            ['userId' => $userId],
        );
    }
}
