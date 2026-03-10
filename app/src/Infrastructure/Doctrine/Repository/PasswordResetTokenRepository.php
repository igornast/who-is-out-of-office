<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\PasswordResetToken;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\User\Repository\PasswordResetTokenRepositoryInterface;
use App\Shared\DTO\PasswordResetTokenDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class PasswordResetTokenRepository extends ServiceEntityRepository implements PasswordResetTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findOneByToken(string $token): ?PasswordResetTokenDTO
    {
        $entity = $this->findOneBy(['token' => $token]);

        return $entity instanceof PasswordResetToken ? PasswordResetTokenDTO::fromEntity($entity) : null;
    }

    public function save(string $token, string $userId, \DateTimeImmutable $expiresAt): void
    {
        $em = $this->getEntityManager();
        /** @var User $user */
        $user = $em->getReference(User::class, $userId);

        $entity = new PasswordResetToken(
            id: Uuid::uuid4(),
            token: $token,
            user: $user,
            expiresAt: $expiresAt,
        );

        $em->persist($entity);
        $em->flush();
    }

    public function removeByUserId(string $userId): void
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();
        $qb->delete(PasswordResetToken::class, 'prt')
            ->where('prt.user = :userId')
            ->setParameter('userId', $userId);
        $qb->getQuery()->execute();
    }

    public function removeByToken(string $token): void
    {
        $entity = $this->findOneBy(['token' => $token]);

        if (!$entity instanceof PasswordResetToken) {
            return;
        }

        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }

    public function removeExpired(): int
    {
        $em = $this->getEntityManager();
        $qb = $em->createQueryBuilder();
        $qb->delete(PasswordResetToken::class, 'prt')
            ->where('prt.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable());

        return (int) $qb->getQuery()->execute();
    }
}
