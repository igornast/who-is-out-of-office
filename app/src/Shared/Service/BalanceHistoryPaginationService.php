<?php

declare(strict_types=1);

namespace App\Shared\Service;

use App\Shared\DTO\Balance\LeavePeriodDTO;

class BalanceHistoryPaginationService
{
    private const int PAGE_SIZE = 50;

    /**
     * @param LeavePeriodDTO[] $periods
     */
    public function resolvePeriod(array $periods, string $requestedPeriodId): LeavePeriodDTO
    {
        foreach ($periods as $period) {
            if ($period->id === $requestedPeriodId) {
                return $period;
            }
        }

        foreach ($periods as $period) {
            if ($period->isOpen()) {
                return $period;
            }
        }

        return $periods[0];
    }

    public function resolvePage(mixed $requestedPage): ?int
    {
        $page = filter_var($requestedPage, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
                'max_range' => intdiv(PHP_INT_MAX, self::PAGE_SIZE),
            ],
        ]);

        return false === $page ? null : $page;
    }

    public function pageSize(): int
    {
        return self::PAGE_SIZE;
    }
}
