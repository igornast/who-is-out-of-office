<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\Holiday;

use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class PublicHolidayDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): PublicHolidayDTO
    {
        return new PublicHolidayDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'id' => $faker->uuid(),
            'description' => $faker->text(),
            'countryCode' => $faker->countryCode(),
            'date' => \DateTimeImmutable::createFromMutable($faker->dateTime()),
            'isGlobal' => true,
            'counties' => null,
        ];
    }
}
