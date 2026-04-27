<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\Feed;

use App\Shared\DTO\Feed\FeedItemDTO;
use App\Shared\Enum\FeedItemTypeEnum;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class FeedItemDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): FeedItemDTO
    {
        return new FeedItemDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'id' => $faker->uuid(),
            'externalId' => $faker->slug(),
            'title' => $faker->sentence(),
            'url' => $faker->url(),
            'contentType' => $faker->randomElement([
                FeedItemTypeEnum::Blog,
                FeedItemTypeEnum::Changelog,
                FeedItemTypeEnum::Announcement,
            ]),
            'publishedAt' => \DateTimeImmutable::createFromMutable($faker->dateTimeThisYear()),
            'summary' => $faker->optional()->paragraph(),
        ];
    }
}
