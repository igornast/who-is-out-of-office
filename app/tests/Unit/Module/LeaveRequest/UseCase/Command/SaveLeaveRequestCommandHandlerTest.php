<?php

declare(strict_types=1);

use App\Module\LeaveRequest\Event\LeaveRequestSavedEvent;
use App\Module\LeaveRequest\Repository\LeaveRequestRepositoryInterface;
use App\Module\LeaveRequest\UseCase\Command\SaveLeaveRequestCommandHandler;
use App\Shared\Facade\UserFacadeInterface;
use App\Shared\Handler\LeaveRequest\Command\SaveLeaveRequestCommand;
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

    $this->repository
        ->expects('beginTransaction')
        ->once();

    $this->repository
        ->expects('saveLeaveRequest')
        ->once();

    $this->userFacade
        ->expects('updateUserCurrentLeaveBalance')
        ->once()
        ->with('user-123', -4);

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
        ->expects('updateUserCurrentLeaveBalance')
        ->never();

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
        ->expects('updateUserCurrentLeaveBalance')
        ->once()
        ->with('user-999', -3)
        ->andThrow($expectedException);

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
        ->toThrow(RuntimeException::class, 'Balance update failed');
});
