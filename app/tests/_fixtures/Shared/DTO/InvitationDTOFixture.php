<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO;

use App\Shared\DTO\InvitationDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class InvitationDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): InvitationDTO
    {
        return new InvitationDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'id' => $faker->uuid(),
            'token' => $faker->sha256(),
            'user' => UserDTOFixture::create(),
            'createdAt' => new \DateTimeImmutable(),
        ];
    }
}
