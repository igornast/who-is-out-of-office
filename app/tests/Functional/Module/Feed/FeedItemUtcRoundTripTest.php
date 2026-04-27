<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\FeedItem;
use App\Infrastructure\Doctrine\Repository\FeedItemRepository;
use App\Tests\_fixtures\Shared\DTO\Feed\FeedItemDTOFixture;

beforeEach(function (): void {
    static::bootKernel();
    $this->repository = static::getContainer()->get(FeedItemRepository::class);
    $this->em = static::getContainer()->get('doctrine')->getManager();

    foreach ($this->em->getRepository(FeedItem::class)->findAll() as $item) {
        $this->em->remove($item);
    }
    $this->em->flush();
});

it('round-trips publishedAt as the same UTC instant through Doctrine', function (): void {
    $publishedAt = new DateTimeImmutable('2026-04-27T16:42:34Z');
    // Computed once and hardcoded so a future TZ regression on the test machine cannot silently mask drift.
    // Verified: (new \DateTimeImmutable('2026-04-27T16:42:34Z'))->getTimestamp() === 1777308154
    $expectedTimestamp = 1777308154;

    $dto = FeedItemDTOFixture::create([
        'externalId' => 'utc-roundtrip-test',
        'publishedAt' => $publishedAt,
    ]);

    $this->repository->upsertMany([$dto]);
    $this->em->clear();

    $entity = $this->em->getRepository(FeedItem::class)->findOneBy(['externalId' => 'utc-roundtrip-test']);

    expect($entity)->not->toBeNull()
        ->and($entity->publishedAt->getTimestamp())->toBe($expectedTimestamp)
        ->and($entity->publishedAt->getTimezone()->getName())->toBeIn(['UTC', '+00:00']);
});
