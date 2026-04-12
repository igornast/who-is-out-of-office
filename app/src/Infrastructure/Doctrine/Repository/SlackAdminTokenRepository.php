<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\SlackAdminToken;
use App\Infrastructure\Security\SlackTokenEncryptor;
use App\Infrastructure\Slack\Repository\SlackAdminTokenRepositoryInterface;
use App\Shared\DTO\Slack\SlackAdminTokenDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<SlackAdminToken>
 */
class SlackAdminTokenRepository extends ServiceEntityRepository implements SlackAdminTokenRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly SlackTokenEncryptor $encryptor,
    ) {
        parent::__construct($registry, SlackAdminToken::class);
    }

    public function findCurrent(): ?SlackAdminTokenDTO
    {
        $entity = $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$entity instanceof SlackAdminToken) {
            return null;
        }

        return new SlackAdminTokenDTO(
            id: $entity->id->toString(),
            plainToken: $this->encryptor->decrypt($entity->encryptedToken),
            slackUserId: $entity->slackUserId,
            createdAt: $entity->getCreatedAt(),
        );
    }

    public function save(string $encryptedToken, string $slackUserId): void
    {
        $this->deleteAll();

        $entity = new SlackAdminToken(
            id: Uuid::uuid4(),
            encryptedToken: $encryptedToken,
            slackUserId: $slackUserId,
        );

        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function deleteAll(): void
    {
        $em = $this->getEntityManager();
        $em->createQuery('DELETE FROM '.SlackAdminToken::class.' t')->execute();
    }
}
