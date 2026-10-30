<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO;

use App\Shared\DTO\Balance\LeavePeriodDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class LeavePeriodDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): LeavePeriodDTO
    {
        return new LeavePeriodDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'id' => $faker->uuid(),
            'userId' => $faker->uuid(),
            'allowanceGranted' => 24,
            'openingBalance' => 0,
            'startDate' => new \DateTimeImmutable('-6 months'),
            'endDate' => new \DateTimeImmutable('+6 months'),
            'closingBalance' => null,
            'closedAt' => null,
        ];
    }
}
