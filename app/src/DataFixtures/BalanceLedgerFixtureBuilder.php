<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\LeavePeriod;
use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Enum\RoleEnum;
use Ramsey\Uuid\Uuid;

class BalanceLedgerFixtureBuilder
{
    /**
     * @param array<string, object> $objects
     *
     * @return list<LeavePeriod|BalanceLedgerEntry>
     */
    public function build(array $objects): array
    {
        $users = [];
        $leaveRequests = [];

        foreach ($objects as $object) {
            if ($object instanceof User) {
                $users[] = $object;
            }
            if ($object instanceof LeaveRequest) {
                $leaveRequests[] = $object;
            }
        }

        $admin = array_values(array_filter(
            $users,
            static fn (User $user): bool => in_array(RoleEnum::Admin->value, $user->roles, true),
        ))[0] ?? null;

        if (!$admin instanceof User) {
            throw new \LogicException('No admin user found in fixtures.');
        }

        $entities = [];
        $year = (int) date('Y');

        foreach ($users as $user) {
            $period = new LeavePeriod(
                allowanceGranted: $user->annualLeaveAllowance,
                openingBalance: $user->annualLeaveAllowance,
                id: Uuid::uuid4(),
                user: $user,
                startDate: new \DateTimeImmutable(sprintf('%d-01-01', $year)),
                endDate: new \DateTimeImmutable(sprintf('%d-01-01', $year + 1)),
            );
            $entities[] = $period;

            $balance = 0;
            $sequence = 1;
            $entities[] = $this->entry(
                delta: $user->annualLeaveAllowance,
                balanceBefore: $balance,
                balanceAfter: $balance + $user->annualLeaveAllowance,
                sequence: $sequence++,
                type: BalanceLedgerEntryTypeEnum::OpeningBalance,
                user: $user,
                period: $period,
            );
            $balance += $user->annualLeaveAllowance;

            $userLeaveRequests = array_values(array_filter(
                $leaveRequests,
                static fn (LeaveRequest $leaveRequest): bool => $leaveRequest->user === $user
                    && $leaveRequest->leaveType->isAffectingBalance,
            ));
            usort(
                $userLeaveRequests,
                static fn (LeaveRequest $left, LeaveRequest $right): int => $left->startDate <=> $right->startDate,
            );

            foreach ($userLeaveRequests as $leaveRequest) {
                $delta = -$leaveRequest->workDays;
                $entities[] = $this->entry(
                    delta: $delta,
                    balanceBefore: $balance,
                    balanceAfter: $balance + $delta,
                    sequence: $sequence++,
                    type: BalanceLedgerEntryTypeEnum::Usage,
                    user: $user,
                    period: $period,
                    actor: $user,
                    leaveRequest: $leaveRequest,
                );
                $balance += $delta;

                $restorationActor = match ($leaveRequest->status) {
                    LeaveRequestStatusEnum::Rejected,
                    LeaveRequestStatusEnum::Cancelled => $leaveRequest->approvedBy ?? $admin,
                    LeaveRequestStatusEnum::Withdrawn => $user,
                    default => null,
                };

                if ($restorationActor instanceof User) {
                    $entities[] = $this->entry(
                        delta: $leaveRequest->workDays,
                        balanceBefore: $balance,
                        balanceAfter: $balance + $leaveRequest->workDays,
                        sequence: $sequence++,
                        type: BalanceLedgerEntryTypeEnum::Restoration,
                        user: $user,
                        period: $period,
                        actor: $restorationActor,
                        leaveRequest: $leaveRequest,
                    );
                    $balance += $leaveRequest->workDays;
                }
            }

            if ($balance !== $user->currentLeaveBalance) {
                $delta = $user->currentLeaveBalance - $balance;
                $entities[] = $this->entry(
                    delta: $delta,
                    balanceBefore: $balance,
                    balanceAfter: $balance + $delta,
                    sequence: $sequence,
                    type: BalanceLedgerEntryTypeEnum::ManualAdjustment,
                    user: $user,
                    period: $period,
                    reason: $delta > 0 ? 'Carry-over from last year' : 'Leave taken before tracking started',
                    actor: $admin,
                );
            }
        }

        return $entities;
    }

    private function entry(
        int $delta,
        int $balanceBefore,
        int $balanceAfter,
        int $sequence,
        BalanceLedgerEntryTypeEnum $type,
        User $user,
        LeavePeriod $period,
        ?string $reason = null,
        ?User $actor = null,
        ?LeaveRequest $leaveRequest = null,
    ): BalanceLedgerEntry {
        return new BalanceLedgerEntry(
            delta: $delta,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            sequence: $sequence,
            id: Uuid::uuid4(),
            type: $type,
            source: BalanceLedgerSourceEnum::App,
            user: $user,
            leavePeriod: $period,
            reason: $reason,
            actor: $actor,
            leaveRequest: $leaveRequest,
        );
    }
}
