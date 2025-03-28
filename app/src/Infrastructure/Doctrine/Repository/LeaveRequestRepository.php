<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Shared\DTO\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LeaveRequestRepository extends ServiceEntityRepository implements LeaveRequestRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveRequest::class);
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
        $now = new \DateTimeImmutable();
        $endInterval = $now->modify('+30 days');

        $qb = $this->createQueryBuilder('lr');
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
}
