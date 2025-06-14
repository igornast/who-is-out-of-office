<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
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

        return $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.id = :id')
            ->setParameter('id', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return LeaveRequestDTO[]
     */
    public function findForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate, array $statuses): array
    {
        $qb = $this->createQueryBuilder('lr');
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

    public function delete(LeaveRequestDTO $leaveRequestDTO): void
    {
        $qb = $this->createQueryBuilder('lr');
        $qb->delete()
            ->where('lr.id = :id')
            ->setParameter('id', $leaveRequestDTO->id)
            ->getQuery()
            ->execute();
    }
}
