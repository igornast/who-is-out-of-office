<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO;

use App\Shared\DTO\UserDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class UserDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): UserDTO
    {
        return new UserDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'id' => $faker->uuid(),
            'firstName' => $faker->firstName(),
            'lastName' => $faker->lastName(),
            'email' => $faker->email(),
            'roles' => ['ROLE_USER'],
            'workingDays' => [1, 2, 3, 4, 5],
            'annualLeaveAllowance' => 24,
            'currentLeaveBalance' => 24,
            'isActive' => true,
            'createdAt' => $faker->dateTime(),
            'password' => $faker->password(),
            'profileImageUrl' => null,
            'slackMemberId' => null,
            'calendarCountryCode' => $faker->countryCode(),
            'birthDate' => $faker->dateTimeThisDecade(),
        ];
    }
}
