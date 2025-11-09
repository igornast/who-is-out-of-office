<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\Holiday;

use App\Shared\DTO\Holiday\UserPublicHolidaysDTO;
use App\Tests\_fixtures\FixtureInterface;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

class UserPublicHolidaysDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): UserPublicHolidaysDTO
    {
        return new UserPublicHolidaysDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        return [
            'user' => UserDTOFixture::create(),
            'holidays' => [
                PublicHolidayDTOFixture::create(),
            ],
        ];
    }
}
