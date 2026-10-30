<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Command\UpdateLeaveRequestCommandHandler;
use App\Module\LeaveRequest\UseCase\Command\UpdateLeaveRequestWithBalanceCommandHandler;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\LeaveRequestStatusEnum;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestDTOFixture;

beforeEach(function (): void {
    $this->leaveRequestRepository = mock(LeaveRequestRepositoryInterface::class);
    $this->updateHandler = mock(UpdateLeaveRequestCommandHandler::class);
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->handler = new UpdateLeaveRequestWithBalanceCommandHandler(
        leaveRequestRepository: $this->leaveRequestRepository,
        updateLeaveRequestHandler: $this->updateHandler,
        userFacade: $this->userFacade,
    );
});

it('updates and restores from the locked request values when usage exists', function (): void {
    $requested = LeaveRequestDTOFixture::create(['status' => LeaveRequestStatusEnum::Rejected, 'workDays' => 99]);
    $current = LeaveRequestDTOFixture::create(['id' => $requested->id, 'status' => LeaveRequestStatusEnum::Pending, 'workDays' => 5]);
    $this->leaveRequestRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->leaveRequestRepository->expects('beginTransaction')->once();
    $this->leaveRequestRepository->expects('findByIdForUpdate')->once()->andReturn($current);
    $this->updateHandler->expects('handle')->once()->with($requested);
    $this->userFacade->expects('shouldRestoreBalanceForLeaveRequest')->once()->withArgs(
        fn (string $leaveRequestId, string $userId, ?DateTimeImmutable $createdAt, bool $isAffectingBalance): bool => $leaveRequestId === $current->id->toString()
            && $userId === $current->user->id
            && $createdAt === $current->createdAt
            && $isAffectingBalance === $current->leaveType->isAffectingBalance,
    )->andReturnTrue();
    $this->userFacade->expects('recordBalanceChange')->once()->withArgs(
        fn (RecordBalanceChangeCommand $command): bool => 5 === $command->delta
            && $command->userId === $current->user->id
            && $command->leaveRequestId === $current->id->toString()
            && BalanceLedgerEntryTypeEnum::Restoration === $command->type,
    );
    $this->leaveRequestRepository->expects('commit')->once();

    expect($this->handler->handle($requested))->toBeTrue();
});

it('does not restore when no usage entry exists', function (): void {
    $requested = LeaveRequestDTOFixture::create(['status' => LeaveRequestStatusEnum::Rejected]);
    $current = LeaveRequestDTOFixture::create(['id' => $requested->id, 'status' => LeaveRequestStatusEnum::Pending]);
    $this->leaveRequestRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->leaveRequestRepository->expects('beginTransaction')->once();
    $this->leaveRequestRepository->expects('findByIdForUpdate')->once()->andReturn($current);
    $this->updateHandler->expects('handle')->once()->with($requested);
    $this->userFacade->expects('shouldRestoreBalanceForLeaveRequest')->once()->andReturnFalse();
    $this->userFacade->expects('recordBalanceChange')->never();
    $this->leaveRequestRepository->expects('commit')->once();

    expect($this->handler->handle($requested))->toBeTrue();
});

it('returns false for an already processed request', function (): void {
    $requested = LeaveRequestDTOFixture::create(['status' => LeaveRequestStatusEnum::Rejected]);
    $current = LeaveRequestDTOFixture::create(['id' => $requested->id, 'status' => LeaveRequestStatusEnum::Rejected]);
    $this->leaveRequestRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->leaveRequestRepository->expects('beginTransaction')->once();
    $this->leaveRequestRepository->expects('findByIdForUpdate')->once()->andReturn($current);
    $this->updateHandler->expects('handle')->never();
    $this->leaveRequestRepository->expects('commit')->once();

    expect($this->handler->handle($requested))->toBeFalse();
});

it('rolls back when the update fails', function (): void {
    $requested = LeaveRequestDTOFixture::create(['status' => LeaveRequestStatusEnum::Rejected]);
    $current = LeaveRequestDTOFixture::create(['id' => $requested->id, 'status' => LeaveRequestStatusEnum::Pending]);
    $exception = new RuntimeException('failure');
    $this->leaveRequestRepository->expects('isTransactionActive')->once()->andReturn(false);
    $this->leaveRequestRepository->expects('beginTransaction')->once();
    $this->leaveRequestRepository->expects('findByIdForUpdate')->once()->andReturn($current);
    $this->updateHandler->expects('handle')->once()->andThrow($exception);
    $this->leaveRequestRepository->expects('rollback')->once();
    $this->leaveRequestRepository->expects('commit')->never();

    expect(fn () => $this->handler->handle($requested))->toThrow($exception);
});

it('throws when the request is missing', function (): void {
    $requested = LeaveRequestDTOFixture::create(['status' => LeaveRequestStatusEnum::Rejected]);
    $this->leaveRequestRepository->expects('isTransactionActive')->once()->andReturn(true);
    $this->leaveRequestRepository->expects('findByIdForUpdate')->once()->andReturn(null);

    expect(fn () => $this->handler->handle($requested))->toThrow(DomainException::class);
});
