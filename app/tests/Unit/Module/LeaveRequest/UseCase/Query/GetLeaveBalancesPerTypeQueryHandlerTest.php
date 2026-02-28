<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Query\GetLeaveBalancesPerTypeQueryHandler;
use App\Shared\DTO\Dashboard\LeaveBalanceDTO;

beforeEach(function (): void {
    $this->repository = mock(LeaveRequestRepositoryInterface::class);
    $this->handler = new GetLeaveBalancesPerTypeQueryHandler(repository: $this->repository);
});

it('maps repository rows to LeaveBalanceDTO objects', function () {
    $userId = 'user-1';
    $periodStart = new DateTimeImmutable('2026-01-01');

    $this->repository
        ->expects('findUsedDaysPerTypeForUser')
        ->once()
        ->with($userId, $periodStart)
        ->andReturn([
            [
                'leave_type_name' => 'Vacation',
                'leave_type_icon' => '🌴',
                'background_color' => '#d4edda',
                'border_color' => '#28a745',
                'text_color' => '#000000',
                'is_affecting_balance' => 1,
                'used_days' => 5,
            ],
            [
                'leave_type_name' => 'Sick Leave',
                'leave_type_icon' => '🤒',
                'background_color' => '#ede7f6',
                'border_color' => '#db9e9e',
                'text_color' => '#4527a0',
                'is_affecting_balance' => 0,
                'used_days' => 2,
            ],
        ]);

    $result = $this->handler->handle($userId, $periodStart);

    expect($result)->toBeArray()
        ->and($result)->toHaveCount(2)
        ->and($result[0])->toBeInstanceOf(LeaveBalanceDTO::class)
        ->and($result[0]->leaveTypeName)->toBe('Vacation')
        ->and($result[0]->leaveTypeIcon)->toBe('🌴')
        ->and($result[0]->isAffectingBalance)->toBeTrue()
        ->and($result[0]->usedDays)->toBe(5)
        ->and($result[1]->leaveTypeName)->toBe('Sick Leave')
        ->and($result[1]->isAffectingBalance)->toBeFalse()
        ->and($result[1]->usedDays)->toBe(2);
});

it('returns empty array when no leave balances exist', function () {
    $userId = 'user-no-leave';
    $periodStart = new DateTimeImmutable('2026-01-01');

    $this->repository
        ->expects('findUsedDaysPerTypeForUser')
        ->once()
        ->with($userId, $periodStart)
        ->andReturn([]);

    $result = $this->handler->handle($userId, $periodStart);

    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

it('casts used_days to integer and is_affecting_balance to boolean', function () {
    $userId = 'user-1';
    $periodStart = new DateTimeImmutable('2026-01-01');

    $this->repository
        ->expects('findUsedDaysPerTypeForUser')
        ->once()
        ->andReturn([
            [
                'leave_type_name' => 'Vacation',
                'leave_type_icon' => '🌴',
                'background_color' => '#d4edda',
                'border_color' => '#28a745',
                'text_color' => '#000000',
                'is_affecting_balance' => '1',
                'used_days' => '3',
            ],
        ]);

    $result = $this->handler->handle($userId, $periodStart);

    expect($result[0]->usedDays)->toBeInt()->toBe(3)
        ->and($result[0]->isAffectingBalance)->toBeBool()->toBeTrue();
});
