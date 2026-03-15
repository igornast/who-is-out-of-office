<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO;

use App\Shared\DTO\PasswordResetTokenDTO;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class PasswordResetTokenDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): PasswordResetTokenDTO
    {
        return new PasswordResetTokenDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'id' => $faker->uuid(),
            'token' => bin2hex(random_bytes(32)),
            'user' => UserDTOFixture::create(),
            'expiresAt' => new \DateTimeImmutable('+1 hour'),
            'createdAt' => new \DateTimeImmutable(),
        ];
    }
}
