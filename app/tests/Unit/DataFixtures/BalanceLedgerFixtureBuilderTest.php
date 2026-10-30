<?php

declare(strict_types=1);

use App\DataFixtures\BalanceLedgerFixtureBuilder;
use App\Infrastructure\Doctrine\Entity\BalanceLedgerEntry;
use App\Infrastructure\Doctrine\Entity\LeavePeriod;
use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\User;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\LeaveRequestStatusEnum;
use Nelmio\Alice\Loader\NativeLoader;

beforeEach(function (): void {
    $this->objects = new NativeLoader()->loadFile(srcDirectory('/DataFixtures/fixtures.yaml'))->getObjects();
    $this->entities = new BalanceLedgerFixtureBuilder()->build($this->objects);

    $this->users = array_values(array_filter($this->objects, fn (object $object): bool => $object instanceof User));
    $this->periods = array_values(array_filter($this->entities, fn (object $entity): bool => $entity instanceof LeavePeriod));
    $this->entries = array_values(array_filter($this->entities, fn (object $entity): bool => $entity instanceof BalanceLedgerEntry));
});

it('opens one period per fixture user ending on their reset day', function (): void {
    expect($this->users)->not->toBeEmpty()
        ->and($this->periods)->toHaveCount(count($this->users));

    foreach ($this->users as $user) {
        $periods = array_values(array_filter($this->periods, fn (LeavePeriod $period): bool => $period->user === $user));

        expect($periods)->toHaveCount(1)
            ->and($periods[0]->closedAt)->toBeNull()
            ->and($periods[0]->openingBalance)->toBe($user->annualLeaveAllowance)
            ->and($periods[0]->endDate)->toEqual($user->absenceBalanceResetDay);
    }
});

it('chains every user ledger to their current balance', function (): void {
    foreach ($this->users as $user) {
        $entries = array_values(array_filter($this->entries, fn (BalanceLedgerEntry $entry): bool => $entry->user === $user));
        $balance = 0;

        expect($entries)->not->toBeEmpty();

        foreach ($entries as $index => $entry) {
            expect($entry->sequence)->toBe($index + 1)
                ->and($entry->balanceBefore)->toBe($balance)
                ->and($entry->balanceAfter)->toBe($balance + $entry->delta);

            $balance = $entry->balanceAfter;

            if (BalanceLedgerEntryTypeEnum::ManualAdjustment === $entry->type) {
                expect($entry->reason)->not->toBeNull()
                    ->and($entry->actor)->toBeInstanceOf(User::class);
            }
        }

        expect($entries[0]->type)->toBe(BalanceLedgerEntryTypeEnum::OpeningBalance)
            ->and($balance)->toBe($user->currentLeaveBalance);
    }
});

it('books usage for every balance-affecting request and restores the released ones', function (): void {
    $leaveRequests = array_filter(
        $this->objects,
        fn (object $object): bool => $object instanceof LeaveRequest && $object->leaveType->isAffectingBalance,
    );
    $releasedStatuses = [LeaveRequestStatusEnum::Rejected, LeaveRequestStatusEnum::Cancelled, LeaveRequestStatusEnum::Withdrawn];

    expect($leaveRequests)->not->toBeEmpty();

    foreach ($leaveRequests as $leaveRequest) {
        $entries = array_filter($this->entries, fn (BalanceLedgerEntry $entry): bool => $entry->leaveRequest === $leaveRequest);
        $usages = array_filter($entries, fn (BalanceLedgerEntry $entry): bool => BalanceLedgerEntryTypeEnum::Usage === $entry->type);
        $restorations = array_filter($entries, fn (BalanceLedgerEntry $entry): bool => BalanceLedgerEntryTypeEnum::Restoration === $entry->type);

        $isReleased = in_array($leaveRequest->status, $releasedStatuses, true);

        expect($usages)->toHaveCount(1)
            ->and(array_values($usages)[0]->delta)->toBe(-$leaveRequest->workDays)
            ->and($restorations)->toHaveCount($isReleased ? 1 : 0);

        foreach ($restorations as $restoration) {
            expect($restoration->delta)->toBe($leaveRequest->workDays);
        }
    }
});
