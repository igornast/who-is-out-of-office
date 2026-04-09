<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Module\LeaveRequest\Repository\LeaveRequestTypeRepositoryInterface;
use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeaveRequestType>
 */
class LeaveRequestTypeRepository extends ServiceEntityRepository implements LeaveRequestTypeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeaveRequestType::class);
    }

    public function findById(string $id): ?LeaveRequestTypeDTO
    {
        $leaveRequest = $this->findOneBy(['id' => $id]);

        return null !== $leaveRequest ? LeaveRequestTypeDTO::fromEntity($leaveRequest) : null;
    }

    /**
     * @return LeaveRequestTypeDTO[]
     */
    public function findAllActive(): array
    {
        $types = $this->createQueryBuilder('t')
            ->addSelect('CASE WHEN t.sort IS NULL THEN 2147483647 ELSE t.sort END AS HIDDEN sortKey')
            ->orderBy('sortKey', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(fn (LeaveRequestType $type) => LeaveRequestTypeDTO::fromEntity($type), $types);
    }
}
