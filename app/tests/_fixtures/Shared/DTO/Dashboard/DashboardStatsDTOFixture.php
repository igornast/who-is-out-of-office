<?php

declare(strict_types=1);

namespace App\Tests\_fixtures\Shared\DTO\Dashboard;

use App\Shared\DTO\Dashboard\DashboardStatsDTO;
use App\Tests\_fixtures\FixtureInterface;

class DashboardStatsDTOFixture implements FixtureInterface
{
    public static function create(array $attributes = []): DashboardStatsDTO
    {
        return new DashboardStatsDTO(...array_merge(self::definitions(), $attributes));
    }

    public static function definitions(): array
    {
        return [
            'pendingRequestsCount' => 2,
            'onLeaveTodayCount' => 1,
            'absencesThisWeekCount' => 3,
        ];
    }
}
