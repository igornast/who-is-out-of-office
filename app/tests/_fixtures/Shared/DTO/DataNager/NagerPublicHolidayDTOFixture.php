<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\DataNager;

use App\Shared\DTO\DataNager\NagerPublicHolidayDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class NagerPublicHolidayDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): NagerPublicHolidayDTO
    {
        return new NagerPublicHolidayDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'date' => \DateTimeImmutable::createFromMutable($faker->dateTimeThisYear()),
            'localName' => $faker->word(),
            'name' => $faker->word(),
            'global' => true,
            'counties' => null,
        ];
    }
}
