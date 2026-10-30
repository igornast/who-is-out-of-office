<?php

declare(strict_types=1);

namespace App\Shared\DTO\Balance;

class BalanceStatementDTO
{
    public function __construct(
        public int $openingBalance,
        public int $granted,
        public int $used,
        public int $restored,
        public int $adjusted,
        public int $currentBalance,
    ) {
    }
}
