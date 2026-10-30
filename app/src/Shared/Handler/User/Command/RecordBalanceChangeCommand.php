<?php

declare(strict_types=1);

namespace App\Shared\Handler\User\Command;

use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;

readonly class RecordBalanceChangeCommand
{
    public function __construct(
        public string $userId,
        public int $delta,
        public BalanceLedgerEntryTypeEnum $type,
        public BalanceLedgerSourceEnum $source,
        public ?string $reason = null,
        public ?string $actorUserId = null,
        public ?string $leaveRequestId = null,
    ) {
    }
}
