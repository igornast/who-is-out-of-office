<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Event\LeaveRequestSavedEvent;
use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Command\SaveLeaveRequestCommandHandler;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Handler\LeaveRequest\Command\SaveLeaveRequestCommand;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use App\Tests\_fixtures\Shared\DTO\LeaveRequest\LeaveRequestTypeDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Psr\EventDispatcher\EventDispatcherInterface;

beforeEach(function (): void {
    $this->userFacade = mock(UserFacadeInterface::class);
    $this->repository = mock(LeaveRequestRepositoryInterface::class);
    $this->dispatcher = mock(EventDispatcherInterface::class);

    $this->handler = new SaveLeaveRequestCommandHandler(
        userFacade: $this->userFacade,
        leaveRequestRepository: $this->repository,
        dispatcher: $this->dispatcher
    );
});

it('saves leave request and updates user balance when leave type affects balance', function () {
    $userDTO = UserDTOFixture::create(['id' => 'user-123']);
    $leaveTypeDTO = LeaveRequestTypeDTOFixture::create(['isAffectingBalance' => true]);

    $command = new SaveLeaveRequestCommand(
        leaveRequestTypeDTO: $leaveTypeDTO,
        startDate: new DateTimeImmutable('2025-01-10'),
        endDate: new DateTimeImmutable('2025-01-15'),
        userDTO: $userDTO
    );

    $workDaysNumber = 4;

    $writeOrder = [];

    $this->repository
        ->expects('beginTransaction')
        ->once();

    $this->repository
        ->expects('saveLeaveRequest')
        ->once()
        ->andReturnUsing(function () use (&$writeOrder): void {
            $writeOrder[] = 'persist request';
        });

    $this->userFacade
        ->expects('recordBalanceChange')
        ->once()
        ->withArgs(
            fn (RecordBalanceChangeCommand $balanceCommand): bool => 'user-123' === $balanceCommand->userId
            && -4 === $balanceCommand->delta
            && BalanceLedgerEntryTypeEnum::Usage === $balanceCommand->type
            && BalanceLedgerSourceEnum::App === $balanceCommand->source
            && 'user-123' === $balanceCommand->actorUserId
            && null !== $balanceCommand->leaveRequestId
        )
        ->andReturnUsing(function () use (&$writeOrder): void {
            expect($writeOrder)->toBe(['persist request']);
            $writeOrder[] = 'lock and record balance';
        });

    $this->repository
        ->expects('flush')
        ->once()
        ->andReturnUsing(function () use (&$writeOrder): void {
            expect($writeOrder)->toBe(['persist request', 'lock and record balance']);
        });

    $this->repository
        ->expects('commit')
        ->once();

    $this->dispatcher
        ->expects('dispatch')
        ->once()
        ->withArgs(fn ($event) => $event instanceof LeaveRequestSavedEvent);

    $this->handler->handle($command, $workDaysNumber);
});

it('saves leave request without updating balance when leave type does not affect balance', function () {
    $userDTO = UserDTOFixture::create(['id' => 'user-456']);
    $leaveTypeDTO = LeaveRequestTypeDTOFixture::create(['isAffectingBalance' => false]);

    $command = new SaveLeaveRequestCommand(
        leaveRequestTypeDTO: $leaveTypeDTO,
        startDate: new DateTimeImmutable('2025-02-01'),
        endDate: new DateTimeImmutable('2025-02-05'),
        userDTO: $userDTO
    );

    $workDaysNumber = 3;

    $this->repository
        ->expects('beginTransaction')
        ->once();

    $this->repository
        ->expects('saveLeaveRequest')
        ->once();

    $this->userFacade
        ->expects('recordBalanceChange')
        ->never();

    $this->repository
        ->expects('flush')
        ->once();

    $this->repository
        ->expects('commit')
        ->once();

    $this->dispatcher
        ->expects('dispatch')
        ->once()
        ->withArgs(fn ($event) => $event instanceof LeaveRequestSavedEvent);

    $this->handler->handle($command, $workDaysNumber);
});

it('rolls back transaction and throws exception when save fails', function () {
    $userDTO = UserDTOFixture::create(['id' => 'user-789']);
    $leaveTypeDTO = LeaveRequestTypeDTOFixture::create(['isAffectingBalance' => true]);

    $command = new SaveLeaveRequestCommand(
        leaveRequestTypeDTO: $leaveTypeDTO,
        startDate: new DateTimeImmutable('2025-03-01'),
        endDate: new DateTimeImmutable('2025-03-10'),
        userDTO: $userDTO
    );

    $workDaysNumber = 7;
    $expectedException = new RuntimeException('Database error');

    $this->repository
        ->expects('beginTransaction')
        ->once();

    $this->repository
        ->expects('saveLeaveRequest')
        ->once()
        ->andThrow($expectedException);

    $this->repository
        ->expects('flush')
        ->never();

    $this->repository
        ->expects('rollback')
        ->once();

    $this->repository
        ->expects('commit')
        ->never();

    $this->dispatcher
        ->expects('dispatch')
        ->never();

    expect(fn () => $this->handler->handle($command, $workDaysNumber))
        ->toThrow(RuntimeException::class, 'Database error');
});

it('rolls back transaction when balance update fails', function () {
    $userDTO = UserDTOFixture::create(['id' => 'user-999']);
    $leaveTypeDTO = LeaveRequestTypeDTOFixture::create(['isAffectingBalance' => true]);

    $command = new SaveLeaveRequestCommand(
        leaveRequestTypeDTO: $leaveTypeDTO,
        startDate: new DateTimeImmutable('2025-04-01'),
        endDate: new DateTimeImmutable('2025-04-05'),
        userDTO: $userDTO
    );

    $workDaysNumber = 3;
    $expectedException = new RuntimeException('Balance update failed');

    $this->repository
        ->expects('beginTransaction')
        ->once();

    $this->repository
        ->expects('saveLeaveRequest')
        ->once();

    $this->userFacade
        ->expects('recordBalanceChange')
        ->once()
        ->withArgs(
            fn (RecordBalanceChangeCommand $balanceCommand): bool => 'user-999' === $balanceCommand->userId
            && -3 === $balanceCommand->delta
            && BalanceLedgerEntryTypeEnum::Usage === $balanceCommand->type
            && BalanceLedgerSourceEnum::App === $balanceCommand->source
            && 'user-999' === $balanceCommand->actorUserId
            && null !== $balanceCommand->leaveRequestId
        )
        ->andThrow($expectedException);

    $this->repository
        ->expects('flush')
        ->never();

    $this->repository
        ->expects('rollback')
        ->once();

    $this->repository
        ->expects('discardPending')
        ->once();

    $this->repository
        ->expects('commit')
        ->never();

    $this->dispatcher
        ->expects('dispatch')
        ->never();

    expect(fn () => $this->handler->handle($command, $workDaysNumber))
        ->toThrow(RuntimeException::class, 'Balance update failed');
});
