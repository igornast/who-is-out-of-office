<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\FeedItem;
use App\Infrastructure\Doctrine\Repository\FeedItemRepository;
use App\Tests\_fixtures\Shared\DTO\Feed\FeedItemDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    static::bootKernel();
    $this->repository = static::getContainer()->get(FeedItemRepository::class);
    $this->em = static::getContainer()->get('doctrine')->getManager();

    foreach ($this->em->getRepository(FeedItem::class)->findAll() as $item) {
        $this->em->remove($item);
    }
    $this->em->flush();
});

it('persists new items using the DTO id', function (): void {
    $knownId = Uuid::uuid4()->toString();
    $dto = FeedItemDTOFixture::create(['id' => $knownId, 'externalId' => 'ext-1']);

    $this->repository->upsertMany([$dto]);

    $entity = $this->em->getRepository(FeedItem::class)->findOneBy(['externalId' => 'ext-1']);
    expect($entity)->not->toBeNull()
        ->and($entity->id->toString())->toBe($knownId);
});

it('updates existing item fields without changing its id', function (): void {
    $originalDto = FeedItemDTOFixture::create(['externalId' => 'ext-upd', 'title' => 'Original']);
    $this->repository->upsertMany([$originalDto]);
    $this->em->clear();

    $originalEntity = $this->em->getRepository(FeedItem::class)->findOneBy(['externalId' => 'ext-upd']);
    $originalEntityId = $originalEntity->id->toString();

    $updatedDto = FeedItemDTOFixture::create(['externalId' => 'ext-upd', 'title' => 'Updated', 'id' => Uuid::uuid4()->toString()]);
    $this->repository->upsertMany([$updatedDto]);
    $this->em->clear();

    $entity = $this->em->getRepository(FeedItem::class)->findOneBy(['externalId' => 'ext-upd']);
    expect($entity->title)->toBe('Updated')
        ->and($entity->id->toString())->toBe($originalEntityId);
});

it('handles a batch of new and existing items in a single upsert', function (): void {
    $existing = FeedItemDTOFixture::create(['externalId' => 'ext-a', 'title' => 'Old A']);
    $this->repository->upsertMany([$existing]);
    $this->em->clear();

    $updatedA = FeedItemDTOFixture::create(['externalId' => 'ext-a', 'title' => 'New A']);
    $newB = FeedItemDTOFixture::create(['externalId' => 'ext-b', 'title' => 'B']);
    $newC = FeedItemDTOFixture::create(['externalId' => 'ext-c', 'title' => 'C']);

    $this->repository->upsertMany([$updatedA, $newB, $newC]);
    $this->em->clear();

    $all = $this->em->getRepository(FeedItem::class)->findAll();
    expect($all)->toHaveCount(3);

    $titles = array_map(fn (FeedItem $e): string => $e->title, $all);
    expect($titles)->toContain('New A')
        ->and($titles)->toContain('B')
        ->and($titles)->toContain('C');
});
