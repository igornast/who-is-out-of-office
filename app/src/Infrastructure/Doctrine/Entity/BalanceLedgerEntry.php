<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use Ramsey\Uuid\UuidInterface;

class BalanceLedgerEntry
{
    use TimestampableTrait;

    public function __construct(
        public int $delta,
        public int $balanceBefore,
        public int $balanceAfter,
        public int $sequence,
        public UuidInterface $id,
        public BalanceLedgerEntryTypeEnum $type,
        public BalanceLedgerSourceEnum $source,
        public User $user,
        public LeavePeriod $leavePeriod,
        public ?string $reason = null,
        public ?User $actor = null,
        public ?LeaveRequest $leaveRequest = null,
    ) {
        $this->initializeTimestamps();
    }
}
