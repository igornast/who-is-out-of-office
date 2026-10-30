<?php

declare(strict_types=1);

namespace App\Shared\Enum;

enum BalanceLedgerEntryTypeEnum: string
{
    case OpeningBalance = 'opening_balance';
    case Accrual = 'accrual';
    case Usage = 'usage';
    case Restoration = 'restoration';
    case ManualAdjustment = 'manual_adjustment';
}
