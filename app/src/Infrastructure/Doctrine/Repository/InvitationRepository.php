<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\Invitation;
use App\Module\User\Repository\InvitationRepositoryInterface;
use App\Shared\DTO\InvitationDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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

    public function remove(InvitationDTO $invitationDTO): void
    {
        $invitation = $this->findOneBy(['token' => $invitationDTO->token]);

        $em = $this->getEntityManager();

        $em->remove($invitation);
        $em->flush();
    }
}
