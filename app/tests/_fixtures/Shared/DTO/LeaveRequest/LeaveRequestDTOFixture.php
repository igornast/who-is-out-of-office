<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\LeaveRequest;

use App\Shared\DTO\LeaveRequest\LeaveRequestDTO;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\LeaveRequestTypeEnum;
use App\Tests\_fixtures\FixtureInterface;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Faker\Factory;

class LeaveRequestDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): LeaveRequestDTO
    {
        return new LeaveRequestDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        $startDate = $faker->dateTimeThisMonth();
        $endDate = $startDate->modify('+'.$faker->numberBetween(1, 20).' days');

        return [
            'id' => $faker->uuid(),
            'workDays' => $faker,
            'status' => $faker->randomElement(LeaveRequestStatusEnum::cases()),
            'leaveType' => $faker->randomElement(LeaveRequestTypeEnum::cases()),
            'startDate' => \DateTimeImmutable::createFromMutable($startDate),
            'endDate' => \DateTimeImmutable::createFromMutable($endDate),
            'user' => UserDTOFixture::create(),
            'approvedBy' => $faker->randomElement([null, UserDTOFixture::create(['roles' => ['ROLE_MANAGER']])->id]),
            'comment' => $faker->sentence(),
        ];
    }
}
