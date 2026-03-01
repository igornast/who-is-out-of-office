<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeaveRequest>
 */
class LeaveRequestRepository extends ServiceEntityRepository implements LeaveRequestRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveRequest::class);
    }

    public function findById(string $id): ?LeaveRequestDTO
    {
        $leaveRequest = $this->findOneBy(['id' => $id]);

        return null !== $leaveRequest ? LeaveRequestDTO::fromEntity($leaveRequest) : null;
    }

    public function saveLeaveRequest(LeaveRequestDTO $leaveRequestDTO): void
    {
        $user = $this->getUserOrNull($leaveRequestDTO->user->id);

        if (is_null($user)) {
            throw new \RuntimeException('User not found');
        }

        $leaveRequestType = $this->getLeaveRequestType($leaveRequestDTO);

        if (null === $leaveRequestType) {
            throw new \RuntimeException('LeaveRequestType not found');
        }

        $leaveRequest = new LeaveRequest(
            id: $leaveRequestDTO->id,
            user: $user,
            status: $leaveRequestDTO->status,
            leaveType: $leaveRequestType,
            startDate: $leaveRequestDTO->startDate,
            endDate: $leaveRequestDTO->endDate,
            workDays: $leaveRequestDTO->workDays,
        );

        $this->getEntityManager()->persist($leaveRequest);
        $this->getEntityManager()->flush();
    }

    public function update(LeaveRequestDTO $leaveRequestDTO): void
    {
        /** @var ?LeaveRequest $leaveRequestEntity */
        $leaveRequestEntity = $this->findOneBy(['id' => $leaveRequestDTO->id]);

        if (null === $leaveRequestEntity) {
            return;
        }

        $approvedBy = null;
        $approver = $leaveRequestDTO->approvedBy;
        if (null !== $approver) {
            $approvedBy = $this->getUserOrNull($approver->id);
        }

        $leaveRequestEntity->status = $leaveRequestDTO->status;
        $leaveRequestEntity->approvedBy = $approvedBy;
        $leaveRequestEntity->isAutoApproved = $leaveRequestDTO->isAutoApproved;

        $this->getEntityManager()->persist($leaveRequestEntity);
        $this->getEntityManager()->flush();
    }

    /**
     * @param LeaveRequestStatusEnum[] $status
     *
     * @return LeaveRequestDTO[]
     */
    public function findForUser(string $userId, array $status): array
    {
        $items = $this->findBy(['user' => $userId, 'status' => $status]);

        return array_map(fn (LeaveRequest $leaveRequest) => LeaveRequestDTO::fromEntity($leaveRequest), $items);
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function findUpcomingApprovedAbsences(int $limit = 4): array
    {
        $now = new \DateTimeImmutable()->setTime(0, 0, 0);
        $endInterval = $now->modify('+30 days');

        $qb = $this->createQueryBuilder('lr');

        /** @var LeaveRequest[] $items */
        $items = $qb->andWhere('lr.status = :approved')
            ->andWhere('lr.startDate BETWEEN :now AND :endInterval')
            ->setParameter('approved', LeaveRequestStatusEnum::Approved->value)
            ->setParameter('now', $now)
            ->setParameter('endInterval', $endInterval)
            ->orderBy('lr.startDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn (LeaveRequest $leaveRequest) => LeaveRequestDTO::fromEntity($leaveRequest), $items);

    }

    private function getUserOrNull(string $userId): ?User
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();

        /** @var User|null $user */
        $user = $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.id = :id')
            ->setParameter('id', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        return $user;
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function findForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array
    {
        $qb = $this->createQueryBuilder('lr');
        /** @var LeaveRequest[] $items */
        $items = $qb->where('lr.status IN (:statuses)')
            ->andWhere('lr.startDate <= :end')
            ->andWhere('lr.endDate   >= :start')
            ->setParameter('statuses', $statuses)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('lr.startDate', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (LeaveRequest $leaveRequest) => LeaveRequestDTO::fromEntity($leaveRequest), $items);
    }

    /**
     * @return array{string, LeaveRequestDTO[]}
     */
    public function findForDatesGroupedByUserId(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array
    {
        $qb = $this->createQueryBuilder('lr');
        /** @var LeaveRequest[] $items */
        $items = $qb->where('lr.status IN (:statuses)')
            ->andWhere('lr.startDate <= :end')
            ->andWhere('lr.endDate   >= :start')
            ->setParameter('statuses', $statuses)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('lr.startDate', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($items as $item) {
            $leaveRequestDTO = LeaveRequestDTO::fromEntity($item);
            $result[$leaveRequestDTO->user->id][] = $leaveRequestDTO;
        }

        return $result;
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function findPendingCreatedBefore(\DateTimeImmutable $createdBefore): array
    {
        $qb = $this->createQueryBuilder('lr');
        /** @var LeaveRequest[] $items */
        $items = $qb->where('lr.status = :status')
            ->andWhere('lr.createdAt <= :createdBefore')
            ->setParameter('status', LeaveRequestStatusEnum::Pending)
            ->setParameter('createdBefore', $createdBefore)
            ->orderBy('lr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (LeaveRequest $leaveRequest) => LeaveRequestDTO::fromEntity($leaveRequest), $items);
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function findOnLeaveToday(): array
    {
        $today = new \DateTimeImmutable('today');

        $qb = $this->createQueryBuilder('lr');

        /** @var LeaveRequest[] $items */
        $items = $qb->where('lr.status = :approved')
            ->andWhere('lr.startDate <= :today')
            ->andWhere('lr.endDate >= :today')
            ->setParameter('approved', LeaveRequestStatusEnum::Approved->value)
            ->setParameter('today', $today)
            ->orderBy('lr.endDate', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (LeaveRequest $leaveRequest) => LeaveRequestDTO::fromEntity($leaveRequest), $items);
    }

    public function countOnLeaveToday(): int
    {
        $today = new \DateTimeImmutable('today');

        $qb = $this->createQueryBuilder('lr');

        /** @var int $count */
        $count = $qb->select('COUNT(DISTINCT IDENTITY(lr.user))')
            ->where('lr.status = :approved')
            ->andWhere('lr.startDate <= :today')
            ->andWhere('lr.endDate >= :today')
            ->setParameter('approved', LeaveRequestStatusEnum::Approved->value)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    public function countAbsencesThisWeek(): int
    {
        $monday = new \DateTimeImmutable('monday this week');
        $sunday = new \DateTimeImmutable('sunday this week');

        $qb = $this->createQueryBuilder('lr');

        /** @var int $count */
        $count = $qb->select('COUNT(DISTINCT IDENTITY(lr.user))')
            ->where('lr.status = :approved')
            ->andWhere('lr.startDate <= :sunday')
            ->andWhere('lr.endDate >= :monday')
            ->setParameter('approved', LeaveRequestStatusEnum::Approved->value)
            ->setParameter('sunday', $sunday)
            ->setParameter('monday', $monday)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    public function countAllPendingRequests(): int
    {
        $qb = $this->createQueryBuilder('lr');

        /** @var int $count */
        $count = $qb->select('COUNT(lr.id)')
            ->where('lr.status = :pending')
            ->setParameter('pending', LeaveRequestStatusEnum::Pending)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    public function findUsedDaysPerTypeForUser(string $userId, \DateTimeImmutable $periodStart): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                lt.name AS leave_type_name,
                lt.icon AS leave_type_icon,
                lt.background_color,
                lt.border_color,
                lt.text_color,
                lt.is_affecting_balance,
                COALESCE(SUM(lr.work_days), 0) AS used_days
            FROM leave_request_type lt
            LEFT JOIN leave_request lr
                ON lr.leave_type = lt.id
                AND lr.user_id = :userId
                AND lr.status = :approved
                AND lr.start_date >= :periodStart
            GROUP BY lt.id
            ORDER BY lt.is_affecting_balance DESC, lt.name ASC
        ';

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('userId', $userId);
        $stmt->bindValue('approved', LeaveRequestStatusEnum::Approved->value);
        $stmt->bindValue('periodStart', $periodStart->format('Y-m-d'));
        $resultSet = $stmt->executeQuery();

        return $resultSet->fetchAllAssociative();
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function findRecentRequests(int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('lr');

        /** @var LeaveRequest[] $items */
        $items = $qb->where('lr.status = :pending')
            ->setParameter('pending', LeaveRequestStatusEnum::Pending)
            ->orderBy('lr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn (LeaveRequest $leaveRequest) => LeaveRequestDTO::fromEntity($leaveRequest), $items);
    }

    public function countAllRequests(): int
    {
        $qb = $this->createQueryBuilder('lr');

        /** @var int $count */
        $count = $qb->select('COUNT(lr.id)')
            ->where('lr.status = :pending')
            ->setParameter('pending', LeaveRequestStatusEnum::Pending)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    public function delete(LeaveRequestDTO $leaveRequestDTO): void
    {
        $qb = $this->createQueryBuilder('lr');
        $qb->delete()
            ->where('lr.id = :id')
            ->setParameter('id', $leaveRequestDTO->id)
            ->getQuery()
            ->execute();
    }

    public function beginTransaction(): void
    {
        $this->getEntityManager()->beginTransaction();
    }

    public function commit(): void
    {
        $this->getEntityManager()->commit();
    }

    public function rollback(): void
    {
        $this->getEntityManager()->rollback();
    }

    private function getLeaveRequestType(LeaveRequestDTO $leaveRequestDTO): ?LeaveRequestType
    {
        return $this->getEntityManager()->getRepository(LeaveRequestType::class)->find($leaveRequestDTO->leaveType->id);
    }
}
