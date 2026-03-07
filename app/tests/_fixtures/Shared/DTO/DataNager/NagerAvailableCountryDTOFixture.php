<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\DataNager;

use App\Shared\DTO\DataNager\NagerAvailableCountryDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class NagerAvailableCountryDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): NagerAvailableCountryDTO
    {
        return new NagerAvailableCountryDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'countryCode' => $faker->countryCode(),
            'name' => $faker->country(),
        ];
    }
}
