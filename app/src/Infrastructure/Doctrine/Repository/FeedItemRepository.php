<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\FeedItem;
use App\Module\Feed\Repository\FeedItemRepositoryInterface;
use App\Shared\DTO\Feed\FeedItemDTO;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

/**
 * @extends ServiceEntityRepository<FeedItem>
 */
class FeedItemRepository extends ServiceEntityRepository implements FeedItemRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeedItem::class);
    }

    public function upsertMany(array $items): void
    {
        if ([] === $items) {
            return;
        }

        $em = $this->getEntityManager();
        $now = new \DateTimeImmutable();

        $externalIds = array_column($items, 'externalId');
        $existingEntities = $this->findBy(['externalId' => $externalIds]);
        $existingMap = [];
        foreach ($existingEntities as $entity) {
            $existingMap[$entity->externalId] = $entity;
        }

        foreach ($items as $dto) {
            if (isset($existingMap[$dto->externalId])) {
                $existing = $existingMap[$dto->externalId];
                $existing->title = $dto->title;
                $existing->url = $dto->url;
                $existing->summary = $dto->summary;
                $existing->contentType = $dto->contentType;
                // Intentional: overwriting publishedAt re-notifies users when the producer re-dates a post. See docs/feed-review.md and JSON Feed producer contract.
                $existing->publishedAt = $dto->publishedAt;
                $existing->fetchedAt = $now;
                $em->persist($existing);

                continue;
            }

            $entity = new FeedItem(
                id: Uuid::fromString($dto->id),
                externalId: $dto->externalId,
                title: $dto->title,
                url: $dto->url,
                contentType: $dto->contentType,
                publishedAt: $dto->publishedAt,
                fetchedAt: $now,
                summary: $dto->summary,
            );
            $em->persist($entity);
        }

        $em->flush();
    }

    public function findRecent(int $limit): array
    {
        $sql = <<<'SQL'
            SELECT id, external_id, title, url, summary, content_type, published_at
            FROM feed_item
            ORDER BY published_at DESC
            LIMIT :limit
        SQL;

        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('limit', $limit, ParameterType::INTEGER);

        $rows = $stmt->executeQuery()->fetchAllAssociative();

        return array_map(fn (array $row): FeedItemDTO => FeedItemDTO::fromArray($row), $rows);
    }

    public function countNewerThan(\DateTimeImmutable $reference): int
    {
        $sql = 'SELECT COUNT(*) AS c FROM feed_item WHERE published_at > :ref';
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $stmt->bindValue('ref', $reference->format('Y-m-d H:i:s'));
        $row = $stmt->executeQuery()->fetchAssociative();

        return (int) ($row['c'] ?? 0);
    }
}
