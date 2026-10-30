<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\LeavePeriod;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\User\Repository\LeavePeriodRepositoryInterface;
use App\Shared\DTO\Balance\LeavePeriodDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<LeavePeriod>
 */
class LeavePeriodRepository extends ServiceEntityRepository implements LeavePeriodRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeavePeriod::class);
    }

    public function findOpenForUser(string $userId): ?LeavePeriodDTO
    {
        $period = $this->createQueryBuilder('lp')
            ->andWhere('IDENTITY(lp.user) = :userId')
            ->andWhere('lp.closedAt IS NULL')
            ->setParameter('userId', $userId)
            ->orderBy('lp.startDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $period instanceof LeavePeriod ? LeavePeriodDTO::fromEntity($period) : null;
    }

    public function findOneById(string $id): ?LeavePeriodDTO
    {
        $period = $this->find($id);

        return $period instanceof LeavePeriod ? LeavePeriodDTO::fromEntity($period) : null;
    }

    /**
     * @return LeavePeriodDTO[]
     */
    public function findAllForUser(string $userId): array
    {
        $periods = $this->createQueryBuilder('lp')
            ->andWhere('IDENTITY(lp.user) = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('lp.startDate', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn (LeavePeriod $period) => LeavePeriodDTO::fromEntity($period), $periods);
    }

    public function add(LeavePeriodDTO $leavePeriodDTO): void
    {
        $em = $this->getEntityManager();
        /** @var User $user */
        $user = $em->getReference(User::class, $leavePeriodDTO->userId);

        $period = new LeavePeriod(
            id: Uuid::fromString($leavePeriodDTO->id),
            allowanceGranted: $leavePeriodDTO->allowanceGranted,
            openingBalance: $leavePeriodDTO->openingBalance,
            user: $user,
            startDate: $leavePeriodDTO->startDate,
            endDate: $leavePeriodDTO->endDate,
            closingBalance: $leavePeriodDTO->closingBalance,
            closedAt: $leavePeriodDTO->closedAt,
        );

        $em->persist($period);
        $em->flush();
    }

    public function close(string $leavePeriodId, int $closingBalance, \DateTimeImmutable $closedAt): void
    {
        $period = $this->find($leavePeriodId);

        if (!$period instanceof LeavePeriod) {
            throw new \DomainException(sprintf('Leave period "%s" not found.', $leavePeriodId));
        }

        $period->closingBalance = $closingBalance;
        $period->closedAt = $closedAt;

        $em = $this->getEntityManager();
        $em->persist($period);
        $em->flush();
    }
}
