<?php

declare(strict_types=1);

namespace App\Shared\DTO\Balance;

use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;

class BalanceLedgerEntryDTO
{
    public function __construct(
        public string $id,
        public string $userId,
        public string $leavePeriodId,
        public int $delta,
        public int $balanceBefore,
        public int $balanceAfter,
        public BalanceLedgerEntryTypeEnum $type,
        public BalanceLedgerSourceEnum $source,
        public \DateTimeImmutable $createdAt,
        public ?string $reason = null,
        public ?string $actorUserId = null,
        public ?string $actorName = null,
        public ?string $leaveRequestId = null,
        public ?string $leaveTypeName = null,
        public ?\DateTimeImmutable $leaveRequestStartDate = null,
        public ?\DateTimeImmutable $leaveRequestEndDate = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            userId: (string) $data['user_id'],
            leavePeriodId: (string) $data['leave_period_id'],
            delta: (int) $data['delta'],
            balanceBefore: (int) $data['balance_before'],
            balanceAfter: (int) $data['balance_after'],
            type: BalanceLedgerEntryTypeEnum::from((string) $data['type']),
            source: BalanceLedgerSourceEnum::from((string) $data['source']),
            createdAt: new \DateTimeImmutable((string) $data['created_at']),
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            actorUserId: isset($data['actor_user_id']) ? (string) $data['actor_user_id'] : null,
            actorName: isset($data['actor_first_name'])
                ? sprintf('%s %s', $data['actor_first_name'], $data['actor_last_name'])
                : null,
            leaveRequestId: isset($data['leave_request_id']) ? (string) $data['leave_request_id'] : null,
            leaveTypeName: isset($data['leave_type_name']) ? (string) $data['leave_type_name'] : null,
            leaveRequestStartDate: isset($data['leave_request_start_date'])
                ? new \DateTimeImmutable((string) $data['leave_request_start_date'])
                : null,
            leaveRequestEndDate: isset($data['leave_request_end_date'])
                ? new \DateTimeImmutable((string) $data['leave_request_end_date'])
                : null,
        );
    }
}
