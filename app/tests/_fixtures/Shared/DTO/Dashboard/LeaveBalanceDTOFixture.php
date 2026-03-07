<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\Dashboard;

use App\Shared\DTO\Dashboard\LeaveBalanceDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class LeaveBalanceDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): LeaveBalanceDTO
    {
        return new LeaveBalanceDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'leaveTypeName' => $faker->word(),
            'leaveTypeIcon' => '',
            'leaveTypeBackgroundColor' => $faker->hexColor(),
            'leaveTypeBorderColor' => $faker->hexColor(),
            'leaveTypeTextColor' => $faker->hexColor(),
            'isAffectingBalance' => $faker->boolean(),
            'usedDays' => $faker->numberBetween(0, 20),
        ];
    }
}
