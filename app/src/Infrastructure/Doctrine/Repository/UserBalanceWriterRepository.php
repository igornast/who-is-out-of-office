<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\User;
use App\Module\User\Repository\UserBalanceWriterInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserBalanceWriterRepository extends ServiceEntityRepository implements UserBalanceWriterInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function updateCurrentLeaveBalance(string $userId, int $currentLeaveBalance): void
    {
        $user = $this->find($userId);

        if (!$user instanceof User) {
            throw new \DomainException(sprintf('User "%s" not found.', $userId));
        }

        $user->currentLeaveBalance = $currentLeaveBalance;
        $this->getEntityManager()->flush();
    }

    public function updateAbsenceBalanceResetDay(string $userId, \DateTimeImmutable $absenceBalanceResetDay): void
    {
        $user = $this->find($userId);

        if (!$user instanceof User) {
            throw new \DomainException(sprintf('User "%s" not found.', $userId));
        }

        $user->absenceBalanceResetDay = $absenceBalanceResetDay;
        $this->getEntityManager()->flush();
    }
}
