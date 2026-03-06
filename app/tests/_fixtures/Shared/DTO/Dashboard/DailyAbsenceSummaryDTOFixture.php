<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\Dashboard;

use App\Shared\DTO\Dashboard\DailyAbsenceSummaryDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class DailyAbsenceSummaryDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): DailyAbsenceSummaryDTO
    {
        return new DailyAbsenceSummaryDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();
        $date = new \DateTimeImmutable();

        return [
            'date' => $date,
            'dayName' => $date->format('l'),
            'dayNumber' => (int) $date->format('j'),
            'isToday' => true,
            'absenceCount' => $faker->numberBetween(0, 5),
            'avatars' => [],
        ];
    }
}
