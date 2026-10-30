<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO;

use App\Shared\DTO\Balance\BalanceLedgerEntryDTO;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Tests\_fixtures\FixtureInterface;
use Faker\Factory;

class BalanceLedgerEntryDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): BalanceLedgerEntryDTO
    {
        return new BalanceLedgerEntryDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        $faker = Factory::create();

        return [
            'id' => $faker->uuid(),
            'userId' => $faker->uuid(),
            'leavePeriodId' => $faker->uuid(),
            'delta' => -1,
            'balanceBefore' => 10,
            'balanceAfter' => 9,
            'type' => BalanceLedgerEntryTypeEnum::Usage,
            'source' => BalanceLedgerSourceEnum::App,
            'createdAt' => new \DateTimeImmutable(),
        ];
    }
}
