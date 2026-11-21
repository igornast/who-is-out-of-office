<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\Holiday;

use App\Shared\DTO\Holiday\HolidayReconciliationDTO;
use App\Tests\_fixtures\FixtureInterface;

class HolidayReconciliationDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): HolidayReconciliationDTO
    {
        return new HolidayReconciliationDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        return [
            'toUpdate' => [],
            'toInsert' => [],
            'toDelete' => [],
        ];
    }
}
