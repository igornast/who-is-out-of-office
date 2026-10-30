<?php

declare(strict_types=1);

use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Module\User\UseCase\Command\AcceptUserInvitationCommandHandler;
use App\Module\User\UseCase\Command\ChangePasswordCommandHandler;
use App\Module\User\UseCase\Command\CleanupExpiredPasswordResetTokensCommandHandler;
use App\Module\User\UseCase\Command\DisableTwoFactorCommandHandler;
use App\Module\User\UseCase\Command\EnableTwoFactorCommandHandler;
use App\Module\User\UseCase\Command\IssueUserInvitationCommandHandler;
use App\Module\User\UseCase\Command\RegenerateBackupCodesCommandHandler;
use App\Module\User\UseCase\Command\CreatePasswordResetTokenCommandHandler;
use App\Module\User\UseCase\Command\DisconnectSlackCommandHandler;
use App\Module\User\UseCase\Command\RegenerateCalendarSubscriptionCommandHandler;
use App\Module\User\UseCase\Command\RemoveProfileImageCommandHandler;
use App\Module\User\UseCase\Command\RecordBalanceChangeCommandHandler;
use App\Module\User\UseCase\Command\OpenInitialLeavePeriodCommandHandler;
use App\Module\User\UseCase\Command\ResetAbsenceBalanceCommandHandler;
use App\Module\User\UseCase\Command\ResetPasswordCommandHandler;
use App\Module\User\UseCase\Command\UpdateCalendarSubscriptionConfigCommandHandler;
use App\Module\User\UseCase\Command\UpdateSlackMemberIdCommandHandler;
use App\Module\User\UseCase\Command\UpdateSlackStatusSyncPreferenceCommandHandler;
use App\Module\User\UseCase\Command\UpdateThemePreferenceCommandHandler;
use App\Module\User\UseCase\Command\UpdateUserFeedLastSeenAtCommandHandler;
use App\Module\User\UseCase\Query\GetAllActiveUsersQueryHandler;
use App\Module\User\UseCase\Query\GetBalanceLedgerForUserQueryHandler;
use App\Module\User\UseCase\Query\GetBalanceStatementQueryHandler;
use App\Module\User\UseCase\Query\GetCalendarSubscriptionConfigQueryHandler;
use App\Module\User\UseCase\Query\GetDirectReportsQueryHandler;
use App\Module\User\UseCase\Query\GetLeavePeriodsForUserQueryHandler;
use App\Module\User\UseCase\Query\ShouldRestoreBalanceForLeaveRequestQueryHandler;
use App\Module\User\UseCase\Query\GetPasswordResetTokenQueryHandler;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use App\Shared\DTO\Balance\BalanceStatementDTO;
use App\Shared\DTO\CalendarSubscription\CalendarSubscriptionConfigDTO;
use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;
use App\Shared\Enum\BalanceLedgerEntryTypeEnum;
use App\Shared\Enum\BalanceLedgerSourceEnum;
use App\Shared\Handler\User\Command\RecordBalanceChangeCommand;
use App\Module\User\UseCase\Query\GetMyTeamUsersQueryHandler;
use App\Module\User\UseCase\Query\GetOrganizationTreeQueryHandler;
use App\Module\User\UseCase\Query\GetUserByIdQueryHandler;
use App\Module\User\UseCase\Query\GetUserBySlackMemberIdQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithBirthdaysForDatesQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithIncomingBirthdaysQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithIncomingWorkAnniversariesQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithWorkAnniversariesForDatesQueryHandler;
use App\Module\User\UseCase\Query\HasPendingInvitationQueryHandler;
use App\Module\User\UserFacade;
use App\Tests\_fixtures\Shared\DTO\InvitationDTOFixture;
use App\Tests\_fixtures\Shared\DTO\BalanceLedgerEntryDTOFixture;
use App\Tests\_fixtures\Shared\DTO\LeavePeriodDTOFixture;
use App\Tests\_fixtures\Shared\DTO\UserDTOFixture;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    $this->recordBalanceChangeHandler = mock(RecordBalanceChangeCommandHandler::class);
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
    $this->updateSlackMemberIdHandler = mock(UpdateSlackMemberIdCommandHandler::class);
    $this->disconnectSlackHandler = mock(DisconnectSlackCommandHandler::class);
    $this->removeProfileImageHandler = mock(RemoveProfileImageCommandHandler::class);
    $this->createPasswordResetTokenHandler = mock(CreatePasswordResetTokenCommandHandler::class);
    $this->resetPasswordHandler = mock(ResetPasswordCommandHandler::class);
    $this->cleanupExpiredPasswordResetTokensHandler = mock(CleanupExpiredPasswordResetTokensCommandHandler::class);
    $this->getPasswordResetTokenHandler = mock(GetPasswordResetTokenQueryHandler::class);
    $this->getAllActiveUsersHandler = mock(GetAllActiveUsersQueryHandler::class);
    $this->updateSlackStatusSyncPreferenceHandler = mock(UpdateSlackStatusSyncPreferenceCommandHandler::class);
    $this->enableTwoFactorHandler = mock(EnableTwoFactorCommandHandler::class);
    $this->disableTwoFactorHandler = mock(DisableTwoFactorCommandHandler::class);
    $this->regenerateBackupCodesHandler = mock(RegenerateBackupCodesCommandHandler::class);
    $this->updateUserFeedLastSeenAtHandler = mock(UpdateUserFeedLastSeenAtCommandHandler::class);
    $this->getCalendarSubscriptionConfigHandler = mock(GetCalendarSubscriptionConfigQueryHandler::class);
    $this->updateCalendarSubscriptionConfigHandler = mock(UpdateCalendarSubscriptionConfigCommandHandler::class);
    $this->getOrganizationTreeHandler = mock(GetOrganizationTreeQueryHandler::class);
    $this->issueUserInvitationHandler = mock(IssueUserInvitationCommandHandler::class);
    $this->hasPendingInvitationHandler = mock(HasPendingInvitationQueryHandler::class);
    $this->openInitialLeavePeriodHandler = mock(OpenInitialLeavePeriodCommandHandler::class);
    $this->getLeavePeriodsForUserHandler = mock(GetLeavePeriodsForUserQueryHandler::class);
    $this->getBalanceLedgerForUserHandler = mock(GetBalanceLedgerForUserQueryHandler::class);
    $this->getBalanceStatementHandler = mock(GetBalanceStatementQueryHandler::class);
    $this->shouldRestoreBalanceForLeaveRequestHandler = mock(ShouldRestoreBalanceForLeaveRequestQueryHandler::class);

    $this->facade = new UserFacade(
        recordBalanceChangeHandler: $this->recordBalanceChangeHandler,
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
        updateSlackMemberIdHandler: $this->updateSlackMemberIdHandler,
        disconnectSlackHandler: $this->disconnectSlackHandler,
        removeProfileImageHandler: $this->removeProfileImageHandler,
        createPasswordResetTokenHandler: $this->createPasswordResetTokenHandler,
        resetPasswordHandler: $this->resetPasswordHandler,
        cleanupExpiredPasswordResetTokensHandler: $this->cleanupExpiredPasswordResetTokensHandler,
        getPasswordResetTokenHandler: $this->getPasswordResetTokenHandler,
        getAllActiveUsersHandler: $this->getAllActiveUsersHandler,
        updateSlackStatusSyncPreferenceHandler: $this->updateSlackStatusSyncPreferenceHandler,
        enableTwoFactorHandler: $this->enableTwoFactorHandler,
        disableTwoFactorHandler: $this->disableTwoFactorHandler,
        regenerateBackupCodesHandler: $this->regenerateBackupCodesHandler,
        updateUserFeedLastSeenAtHandler: $this->updateUserFeedLastSeenAtHandler,
        getCalendarSubscriptionConfigHandler: $this->getCalendarSubscriptionConfigHandler,
        updateCalendarSubscriptionConfigHandler: $this->updateCalendarSubscriptionConfigHandler,
        getOrganizationTreeHandler: $this->getOrganizationTreeHandler,
        issueUserInvitationHandler: $this->issueUserInvitationHandler,
        hasPendingInvitationHandler: $this->hasPendingInvitationHandler,
        openInitialLeavePeriodHandler: $this->openInitialLeavePeriodHandler,
        getLeavePeriodsForUserHandler: $this->getLeavePeriodsForUserHandler,
        getBalanceLedgerForUserHandler: $this->getBalanceLedgerForUserHandler,
        getBalanceStatementHandler: $this->getBalanceStatementHandler,
        shouldRestoreBalanceForLeaveRequestHandler: $this->shouldRestoreBalanceForLeaveRequestHandler,
    );
});

it('delegates recordBalanceChange to handler', function () {
    $command = new RecordBalanceChangeCommand(
        userId: 'user-1',
        delta: -5,
        type: BalanceLedgerEntryTypeEnum::Usage,
        source: BalanceLedgerSourceEnum::App,
        actorUserId: 'actor-1',
        leaveRequestId: 'leave-request-1',
    );

    $this->recordBalanceChangeHandler
        ->expects('handle')
        ->once()
        ->with($command);

    $this->facade->recordBalanceChange($command);
});

it('delegates openInitialLeavePeriod to handler', function () {
    $this->openInitialLeavePeriodHandler
        ->expects('handle')
        ->once()
        ->with('user-1', false);

    $this->facade->openInitialLeavePeriod('user-1');
});

it('delegates getLeavePeriods to handler', function () {
    $periods = [LeavePeriodDTOFixture::create()];
    $this->getLeavePeriodsForUserHandler->expects('handle')->once()->with('user-1')->andReturn($periods);

    expect($this->facade->getLeavePeriods('user-1'))->toBe($periods);
});

it('delegates getBalanceLedger to handler', function () {
    $entries = [BalanceLedgerEntryDTOFixture::create()];
    $this->getBalanceLedgerForUserHandler
        ->expects('handle')
        ->once()
        ->with('user-1', 'period-1', 20, 5)
        ->andReturn($entries);

    expect($this->facade->getBalanceLedger('user-1', 'period-1', 20, 5))->toBe($entries);
});

it('delegates getBalanceStatement to handler', function () {
    $statement = new BalanceStatementDTO(3, 24, -10, 2, 0, 19);
    $this->getBalanceStatementHandler
        ->expects('handle')
        ->once()
        ->with('user-1', 'period-1')
        ->andReturn($statement);

    expect($this->facade->getBalanceStatement('user-1', 'period-1'))->toBe($statement);
});

it('delegates shouldRestoreBalanceForLeaveRequest to handler', function (): void {
    $createdAt = new DateTimeImmutable('-1 day');
    $this->shouldRestoreBalanceForLeaveRequestHandler->expects('handle')
        ->once()
        ->with('leave-request-1', 'user-1', $createdAt, true)
        ->andReturnTrue();

    expect($this->facade->shouldRestoreBalanceForLeaveRequest('leave-request-1', 'user-1', $createdAt, true))->toBeTrue();
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
    $invitationDTO = InvitationDTOFixture::create();

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

it('delegates updateSlackMemberId to handler', function () {
    $this->updateSlackMemberIdHandler
        ->expects('handle')
        ->once()
        ->with('user-1', 'U12345ABC');

    $this->facade->updateSlackMemberId('user-1', 'U12345ABC');
});

it('delegates disconnectSlack to handler', function () {
    $this->disconnectSlackHandler
        ->expects('handle')
        ->once()
        ->with('user-1');

    $this->facade->disconnectSlack('user-1');
});

it('delegates deleteOldProfileImage to handler', function () {
    $this->removeProfileImageHandler
        ->expects('handle')
        ->once()
        ->with('old-avatar.jpg');

    $this->facade->deleteOldProfileImage('old-avatar.jpg');
});

it('delegates createPasswordResetToken to handler', function () {
    $this->createPasswordResetTokenHandler
        ->expects('handle')
        ->once()
        ->with('user@whoisooo.app')
        ->andReturn('reset-token');

    $result = $this->facade->createPasswordResetToken('user@whoisooo.app');

    expect($result)->toBe('reset-token');
});

it('delegates resetPassword to handler', function () {
    $this->resetPasswordHandler
        ->expects('handle')
        ->once()
        ->with('token-abc', 'new-password')
        ->andReturn(true);

    $result = $this->facade->resetPassword('token-abc', 'new-password');

    expect($result)->toBeTrue();
});

it('delegates cleanupExpiredPasswordResetTokens to handler', function () {
    $this->cleanupExpiredPasswordResetTokensHandler
        ->expects('handle')
        ->once()
        ->andReturn(3);

    $result = $this->facade->cleanupExpiredPasswordResetTokens();

    expect($result)->toBe(3);
});

it('delegates getPasswordResetToken to handler', function () {
    $this->getPasswordResetTokenHandler
        ->expects('handle')
        ->once()
        ->with('token-xyz')
        ->andReturn(null);

    $result = $this->facade->getPasswordResetToken('token-xyz');

    expect($result)->toBeNull();
});

it('delegates updateSlackStatusSyncPreference to handler', function () {
    $this->updateSlackStatusSyncPreferenceHandler
        ->expects('handle')
        ->once()
        ->with('user-1', true)
        ->andReturn(true);

    $result = $this->facade->updateSlackStatusSyncPreference('user-1', true);

    expect($result)->toBeTrue();
});

it('delegates enableTwoFactor to handler', function (): void {
    $this->enableTwoFactorHandler
        ->expects('handle')
        ->once()
        ->with('user-1', 'TOTP_SECRET')
        ->andReturn(['code1', 'code2']);

    $result = $this->facade->enableTwoFactor('user-1', 'TOTP_SECRET');

    expect($result)->toBe(['code1', 'code2']);
});

it('delegates disableTwoFactor to handler', function (): void {
    $this->disableTwoFactorHandler
        ->expects('handle')
        ->once()
        ->with('user-1');

    $this->facade->disableTwoFactor('user-1');
});

it('delegates regenerateBackupCodes to handler', function (): void {
    $this->regenerateBackupCodesHandler
        ->expects('handle')
        ->once()
        ->with('user-1')
        ->andReturn(['code1', 'code2']);

    $result = $this->facade->regenerateBackupCodes('user-1');

    expect($result)->toBe(['code1', 'code2']);
});

it('returns true when user has 2FA enabled', function (): void {
    $userDto = UserDTOFixture::create(['id' => 'user-1', 'isTwoFactorEnabled' => true]);
    $this->getUserByIdQueryHandler
        ->expects('handle')
        ->with('user-1')
        ->andReturn($userDto);

    expect($this->facade->isTwoFactorEnabled('user-1'))->toBeTrue();
});

it('returns false when user has 2FA disabled', function (): void {
    $userDto = UserDTOFixture::create(['id' => 'user-1', 'isTwoFactorEnabled' => false]);
    $this->getUserByIdQueryHandler
        ->expects('handle')
        ->with('user-1')
        ->andReturn($userDto);

    expect($this->facade->isTwoFactorEnabled('user-1'))->toBeFalse();
});

it('delegates getAllActiveUsers to handler', function (): void {
    $expectedUsers = [UserDTOFixture::create(), UserDTOFixture::create()];

    $this->getAllActiveUsersHandler
        ->expects('handle')
        ->once()
        ->andReturn($expectedUsers);

    $result = $this->facade->getAllActiveUsers();

    expect($result)->toBe($expectedUsers);
});

it('delegates updateFeedLastSeenAt to handler', function (): void {
    $seenAt = new DateTimeImmutable('2026-05-01 12:00:00');

    $this->updateUserFeedLastSeenAtHandler
        ->expects('handle')
        ->once()
        ->with('user-1', $seenAt);

    $this->facade->updateFeedLastSeenAt('user-1', $seenAt);
});

it('delegates getCalendarSubscriptionConfig to handler', function (): void {
    $config = new CalendarSubscriptionConfigDTO(
        candidateTeamMembers: [],
        candidateHolidayCalendars: [],
        topLevelTeamMemberIds: [],
        myTeamMemberIds: [],
        selectedTeamMemberIds: null,
        selectedHolidayCalendarIds: null,
    );

    $this->getCalendarSubscriptionConfigHandler
        ->expects('handle')
        ->once()
        ->with('user-1')
        ->andReturn($config);

    $result = $this->facade->getCalendarSubscriptionConfig('user-1');

    expect($result)->toBe($config);
});

it('delegates updateCalendarSubscriptionConfig to handler', function (): void {
    $teamMemberIds = ['member-1', 'member-2'];
    $holidayCalendarIds = ['cal-1'];

    $this->updateCalendarSubscriptionConfigHandler
        ->expects('handle')
        ->once()
        ->with('user-1', $teamMemberIds, $holidayCalendarIds);

    $this->facade->updateCalendarSubscriptionConfig('user-1', $teamMemberIds, $holidayCalendarIds);
});

it('delegates issueUserInvitation to its handler', function (): void {
    $userId = Uuid::uuid4()->toString();
    $invitationDTO = InvitationDTOFixture::create();

    $this->issueUserInvitationHandler->expects('handle')->with($userId)->andReturn($invitationDTO);

    expect($this->facade->issueUserInvitation($userId))->toBe($invitationDTO);
});

it('delegates hasPendingInvitation to its handler', function (): void {
    $userId = Uuid::uuid4()->toString();

    $this->hasPendingInvitationHandler->expects('handle')->with($userId)->andReturn(true);

    expect($this->facade->hasPendingInvitation($userId))->toBeTrue();
});
