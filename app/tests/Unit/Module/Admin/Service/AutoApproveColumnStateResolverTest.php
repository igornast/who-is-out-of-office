<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\LeaveRequest;
use App\Infrastructure\Doctrine\Entity\LeaveRequestType;
use App\Infrastructure\Doctrine\Entity\User;
use App\Module\Admin\Service\AutoApproveColumnState;
use App\Module\Admin\Service\AutoApproveColumnStateResolver;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\AppSettingsFacadeInterface;
use Ramsey\Uuid\Uuid;

function buildLeaveRequest(LeaveRequestStatusEnum $status, DateTimeImmutable $createdAt, ?bool $isAutoApproved = false): LeaveRequest
{
    $user = new User(
        id: Uuid::uuid4(),
        firstName: 'Jane',
        lastName: 'Doe',
        email: 'jane@example.com',
        password: 'x',
    );

    $leaveType = new LeaveRequestType(
        id: Uuid::uuid4(),
        isAffectingBalance: true,
        name: 'Holiday',
        backgroundColor: '#fff',
        borderColor: '#000',
        textColor: '#000',
        icon: 'icon',
    );

    $leaveRequest = new LeaveRequest(
        id: Uuid::uuid4(),
        user: $user,
        status: $status,
        leaveType: $leaveType,
        startDate: new DateTimeImmutable('+30 days'),
        endDate: new DateTimeImmutable('+34 days'),
        workDays: 5,
        isAutoApproved: $isAutoApproved,
    );

    $leaveRequest->setCreatedAt($createdAt);

    return $leaveRequest;
}

beforeEach(function (): void {
    $this->appSettingsFacade = mock(AppSettingsFacadeInterface::class);
    $this->resolver = new AutoApproveColumnStateResolver($this->appSettingsFacade);
});

it('resolves a countdown with created-at plus the configured delay for a pending request', function (): void {
    $createdAt = new DateTimeImmutable('2026-06-17 10:00:00');

    $this->appSettingsFacade->expects('autoApproveDelay')->once()->andReturn(120);

    $request = buildLeaveRequest(LeaveRequestStatusEnum::Pending, $createdAt);

    $state = $this->resolver->resolve($request);

    expect($state->kind)->toBe(AutoApproveColumnState::KIND_COUNTDOWN)
        ->and($state->target)->toEqual(new DateTimeImmutable('2026-06-17 12:00:00'));
});

it('resolves auto-approved when the request was auto approved', function (): void {
    $this->appSettingsFacade->expects('autoApproveDelay')->never();

    $request = buildLeaveRequest(LeaveRequestStatusEnum::Approved, new DateTimeImmutable(), isAutoApproved: true);

    $state = $this->resolver->resolve($request);

    expect($state->kind)->toBe(AutoApproveColumnState::KIND_AUTO_APPROVED)
        ->and($state->target)->toBeNull();
});

it('resolves manually-approved for an approved request that was not auto approved', function (): void {
    $this->appSettingsFacade->expects('autoApproveDelay')->never();

    $request = buildLeaveRequest(LeaveRequestStatusEnum::Approved, new DateTimeImmutable());

    $state = $this->resolver->resolve($request);

    expect($state->kind)->toBe(AutoApproveColumnState::KIND_MANUALLY_APPROVED)
        ->and($state->target)->toBeNull();
});

it('resolves none for a rejected request', function (): void {
    $this->appSettingsFacade->expects('autoApproveDelay')->never();

    $request = buildLeaveRequest(LeaveRequestStatusEnum::Rejected, new DateTimeImmutable());

    expect($this->resolver->resolve($request)->kind)->toBe(AutoApproveColumnState::KIND_NONE);
});

it('resolves none for a cancelled request', function (): void {
    $this->appSettingsFacade->expects('autoApproveDelay')->never();

    $request = buildLeaveRequest(LeaveRequestStatusEnum::Cancelled, new DateTimeImmutable());

    expect($this->resolver->resolve($request)->kind)->toBe(AutoApproveColumnState::KIND_NONE);
});

it('resolves none for a withdrawn request', function (): void {
    $this->appSettingsFacade->expects('autoApproveDelay')->never();

    $request = buildLeaveRequest(LeaveRequestStatusEnum::Withdrawn, new DateTimeImmutable());

    expect($this->resolver->resolve($request)->kind)->toBe(AutoApproveColumnState::KIND_NONE);
});
