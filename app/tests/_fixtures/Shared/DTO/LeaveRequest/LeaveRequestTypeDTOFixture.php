<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\LeaveRequest;

use App\Shared\DTO\LeaveRequest\LeaveRequestTypeDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;
use Ramsey\Uuid\Uuid;

class LeaveRequestTypeDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): LeaveRequestTypeDTO
    {
        return new LeaveRequestTypeDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'id' => Uuid::fromString($faker->uuid()),
            'isAffectingBalance' => $faker->boolean(),
            'name' => $faker->word(),
            'backgroundColor' => $faker->hexColor(),
            'borderColor' => $faker->hexColor(),
            'textColor' => $faker->hexColor(),
            'icon' => '',
        ];
    }
}
