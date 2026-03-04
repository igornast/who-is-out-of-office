<?php

declare(strict_types=1);

use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Module\User\UseCase\Command\AcceptUserInvitationCommandHandler;
use App\Module\User\UseCase\Command\ChangePasswordCommandHandler;
use App\Module\User\UseCase\Command\RegenerateCalendarSubscriptionCommandHandler;
use App\Module\User\UseCase\Command\ResetAbsenceBalanceCommandHandler;
use App\Module\User\UseCase\Command\UpdateCurrentLeaveBalanceCommandHandler;
use App\Module\User\UseCase\Command\UpdateThemePreferenceCommandHandler;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use App\Module\User\UseCase\Query\GetDirectReportsQueryHandler;
use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;
use App\Module\User\UseCase\Query\GetMyTeamUsersQueryHandler;
use App\Module\User\UseCase\Query\GetUserByIdQueryHandler;
use App\Module\User\UseCase\Query\GetUserBySlackMemberIdQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithBirthdaysForDatesQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithIncomingBirthdaysQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithIncomingWorkAnniversariesQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithWorkAnniversariesForDatesQueryHandler;
use App\Module\User\UserFacade;
use App\Shared\DTO\InvitationDTO;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;

beforeEach(function (): void {
    $this->updateCurrentLeaveBalanceHandler = mock(UpdateCurrentLeaveBalanceCommandHandler::class);
    $this->getMyTeamUsersHandler = mock(GetMyTeamUsersQueryHandler::class);
    $this->getUsersWithIncomingBirthdaysHandler = mock(GetUsersWithIncomingBirthdaysQueryHandler::class);
    $this->getUserBySlackMemberIdQueryHandler = mock(GetUserBySlackMemberIdQueryHandler::class);
    $this->getUserByIdQueryHandler = mock(GetUserByIdQueryHandler::class);
    $this->getUsersWithBirthdaysForDatesHandler = mock(GetUsersWithBirthdaysForDatesQueryHandler::class);
    $this->acceptInvitationHandler = mock(AcceptUserInvitationCommandHandler::class);
    $this->getUsersWithIncomingWorkAnniversariesHandler = mock(GetUsersWithIncomingWorkAnniversariesQueryHandler::class);
    $this->getUsersWithWorkAnniversariesForDatesHandler = mock(GetUsersWithWorkAnniversariesForDatesQueryHandler::class);
    $this->resetAbsenceBalanceHandler = mock(ResetAbsenceBalanceCommandHandler::class);
    $this->getDirectReportsHandler = mock(GetDirectReportsQueryHandler::class);
    $this->updateThemePreferenceHandler = mock(UpdateThemePreferenceCommandHandler::class);
    $this->changePasswordHandler = mock(ChangePasswordCommandHandler::class);
    $this->regenerateCalendarSubscriptionHandler = mock(RegenerateCalendarSubscriptionCommandHandler::class);

    $this->facade = new UserFacade(
        updateCurrentLeaveBalanceHandler: $this->updateCurrentLeaveBalanceHandler,
        getMyTeamUsersHandler: $this->getMyTeamUsersHandler,
        getUsersWithIncomingBirthdaysHandler: $this->getUsersWithIncomingBirthdaysHandler,
        getUserBySlackMemberIdQueryHandler: $this->getUserBySlackMemberIdQueryHandler,
        getUserByIdQueryHandler: $this->getUserByIdQueryHandler,
        getUsersWithBirthdaysForDatesHandler: $this->getUsersWithBirthdaysForDatesHandler,
        acceptInvitationHandler: $this->acceptInvitationHandler,
        getUsersWithIncomingWorkAnniversariesHandler: $this->getUsersWithIncomingWorkAnniversariesHandler,
        getUsersWithWorkAnniversariesForDatesHandler: $this->getUsersWithWorkAnniversariesForDatesHandler,
        resetAbsenceBalanceHandler: $this->resetAbsenceBalanceHandler,
        getDirectReportsHandler: $this->getDirectReportsHandler,
        updateThemePreferenceHandler: $this->updateThemePreferenceHandler,
        changePasswordHandler: $this->changePasswordHandler,
        regenerateCalendarSubscriptionHandler: $this->regenerateCalendarSubscriptionHandler,
    );
});

it('delegates updateUserCurrentLeaveBalance to handler', function () {
    $this->updateCurrentLeaveBalanceHandler
        ->expects('handle')
        ->once()
        ->with('user-1', -5);

    $this->facade->updateUserCurrentLeaveBalance('user-1', -5);
});

it('delegates getTeamMembersForUserId to handler', function () {
    $expectedUsers = [UserDTOFixture::create()];

    $this->getMyTeamUsersHandler
        ->expects('handle')
        ->once()
        ->with('user-1')
        ->andReturn($expectedUsers);

    $result = $this->facade->getTeamMembersForUserId('user-1');

    expect($result)->toBe($expectedUsers);
});

it('delegates getUsersWithIncomingBirthdays to handler', function () {
    $expectedUsers = [UserDTOFixture::create()];

    $this->getUsersWithIncomingBirthdaysHandler
        ->expects('handle')
        ->once()
        ->andReturn($expectedUsers);

    $result = $this->facade->getUsersWithIncomingBirthdays();

    expect($result)->toBe($expectedUsers);
});

it('delegates getUserBySlackMemberId to handler', function () {
    $expectedUser = UserDTOFixture::create();

    $this->getUserBySlackMemberIdQueryHandler
        ->expects('handle')
        ->once()
        ->with('U12345')
        ->andReturn($expectedUser);

    $result = $this->facade->getUserBySlackMemberId('U12345');

    expect($result)->toBe($expectedUser);
});

it('delegates getUsersWithBirthdaysForDates to handler', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-01-31');
    $expectedUsers = [UserDTOFixture::create()];

    $this->getUsersWithBirthdaysForDatesHandler
        ->expects('handle')
        ->once()
        ->with($start, $end)
        ->andReturn($expectedUsers);

    $result = $this->facade->getUsersWithBirthdaysForDates($start, $end);

    expect($result)->toBe($expectedUsers);
});

it('delegates acceptUserInvitation to handler', function () {
    $invitationRequestDTO = new UserInvitationRequestDTO(
        firstName: 'John',
        lastName: 'Doe',
        password: 'password123',
    );
    $invitationDTO = new InvitationDTO(
        id: 'inv-1',
        token: 'token-123',
        user: UserDTOFixture::create(),
        createdAt: new DateTimeImmutable(),
    );

    $this->acceptInvitationHandler
        ->expects('handle')
        ->once()
        ->with($invitationRequestDTO, $invitationDTO);

    $this->facade->acceptUserInvitation($invitationRequestDTO, $invitationDTO);
});

it('delegates getUser to handler', function () {
    $expectedUser = UserDTOFixture::create();

    $this->getUserByIdQueryHandler
        ->expects('handle')
        ->once()
        ->with('user-1')
        ->andReturn($expectedUser);

    $result = $this->facade->getUser('user-1');

    expect($result)->toBe($expectedUser);
});

it('delegates getUsersWithIncomingWorkAnniversaries to handler', function () {
    $expectedUsers = [UserDTOFixture::create()];

    $this->getUsersWithIncomingWorkAnniversariesHandler
        ->expects('handle')
        ->once()
        ->andReturn($expectedUsers);

    $result = $this->facade->getUsersWithIncomingWorkAnniversaries();

    expect($result)->toBe($expectedUsers);
});

it('delegates getUsersWithWorkAnniversariesForDates to handler', function () {
    $start = new DateTimeImmutable('2025-01-01');
    $end = new DateTimeImmutable('2025-01-07');
    $expectedUsers = [UserDTOFixture::create()];

    $this->getUsersWithWorkAnniversariesForDatesHandler
        ->expects('handle')
        ->once()
        ->with($start, $end)
        ->andReturn($expectedUsers);

    $result = $this->facade->getUsersWithWorkAnniversariesForDates($start, $end);

    expect($result)->toBe($expectedUsers);
});

it('delegates resetAbsenceBalance to handler', function () {
    $this->resetAbsenceBalanceHandler
        ->expects('handle')
        ->once();

    $this->facade->resetAbsenceBalance();
});

it('delegates getDirectReports to handler', function () {
    $expectedUsers = [UserDTOFixture::create()];

    $this->getDirectReportsHandler
        ->expects('handle')
        ->once()
        ->with('manager-1')
        ->andReturn($expectedUsers);

    $result = $this->facade->getDirectReports('manager-1');

    expect($result)->toBe($expectedUsers);
});

it('delegates updateThemePreference to handler', function () {
    $this->updateThemePreferenceHandler
        ->expects('handle')
        ->once()
        ->with('user-1', ThemeEnum::Dark, PaletteEnum::Sage);

    $this->facade->updateThemePreference('user-1', ThemeEnum::Dark, PaletteEnum::Sage);
});

it('delegates changePassword to handler', function () {
    $user = mock(PasswordAuthenticatedUserInterface::class);

    $this->changePasswordHandler
        ->expects('handle')
        ->once()
        ->with('user-1', 'new-password', $user);

    $this->facade->changePassword('user-1', 'new-password', $user);
});

it('delegates regenerateCalendarSubscription to handler', function () {
    $this->regenerateCalendarSubscriptionHandler
        ->expects('handle')
        ->once()
        ->with('user-1');

    $this->facade->regenerateCalendarSubscription('user-1');
});
