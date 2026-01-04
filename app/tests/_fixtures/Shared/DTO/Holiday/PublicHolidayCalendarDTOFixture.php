<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\Holiday;

use App\Shared\DTO\Holiday\PublicHolidayCalendarDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;
use Ramsey\Uuid\Uuid;

class PublicHolidayCalendarDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): PublicHolidayCalendarDTO
    {
        return new PublicHolidayCalendarDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'id' => Uuid::uuid4(),
            'countryCode' => $faker->countryCode(),
            'countryName' => $faker->country(),
            'holidays' => [],
        ];
    }
}
