<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\Invitation;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\User\Repository\InvitationRepositoryInterface;
use App\Shared\DTO\InvitationDTO;
use App\Shared\Exception\ConcurrentInvitationException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository implements InvitationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findOneByToken(string $token): ?InvitationDTO
    {
        $invitation = $this->findOneBy(['token' => $token]);

        return null !== $invitation ? InvitationDTO::fromEntity($invitation) : null;
    }

    public function findOneByUserId(string $id): ?InvitationDTO
    {
        $invitation = $this->findOneBy(['user' => $id]);

        return null !== $invitation ? InvitationDTO::fromEntity($invitation) : null;
    }

    public function replaceForUser(string $userId, string $token): ?InvitationDTO
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->find($userId);

        if (!$user instanceof User) {
            return null;
        }

        try {
            return $em->wrapInTransaction(function () use ($em, $user, $userId, $token): InvitationDTO {
                $existing = $this->findOneBy(['user' => $userId]);

                if ($existing instanceof Invitation) {
                    $em->remove($existing);
                    $em->flush();
                }

                $invitation = new Invitation(
                    id: Uuid::uuid4(),
                    token: $token,
                    user: $user,
                );

                $em->persist($invitation);
                $em->flush();

                return InvitationDTO::fromEntity($invitation);
            });
        } catch (UniqueConstraintViolationException $e) {
            throw new ConcurrentInvitationException($userId, $e);
        }
    }

    public function remove(InvitationDTO $invitationDTO): void
    {
        $invitation = $this->findOneBy(['token' => $invitationDTO->token]);

        if (!$invitation instanceof Invitation) {
            return;
        }

        $em = $this->getEntityManager();

        $em->remove($invitation);
        $em->flush();
    }
}
