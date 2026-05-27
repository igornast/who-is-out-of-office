<?php

declare(strict_types=1);

namespace App\Module\User;

use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Module\User\UseCase\Command\AcceptUserInvitationCommandHandler;
use App\Module\User\UseCase\Command\ResetAbsenceBalanceCommandHandler;
use App\Module\User\UseCase\Command\UpdateCalendarSubscriptionConfigCommandHandler;
use App\Module\User\UseCase\Command\UpdateCurrentLeaveBalanceCommandHandler;
use App\Module\User\UseCase\Command\ChangePasswordCommandHandler;
use App\Module\User\UseCase\Command\DisableTwoFactorCommandHandler;
use App\Module\User\UseCase\Command\DisconnectSlackCommandHandler;
use App\Module\User\UseCase\Command\EnableTwoFactorCommandHandler;
use App\Module\User\UseCase\Command\RegenerateBackupCodesCommandHandler;
use App\Module\User\UseCase\Command\RegenerateCalendarSubscriptionCommandHandler;
use App\Module\User\UseCase\Command\CleanupExpiredPasswordResetTokensCommandHandler;
use App\Module\User\UseCase\Command\CreatePasswordResetTokenCommandHandler;
use App\Module\User\UseCase\Command\RemoveProfileImageCommandHandler;
use App\Module\User\UseCase\Command\ResetPasswordCommandHandler;
use App\Module\User\UseCase\Command\UpdateSlackMemberIdCommandHandler;
use App\Module\User\UseCase\Command\UpdateSlackStatusSyncPreferenceCommandHandler;
use App\Module\User\UseCase\Command\UpdateThemePreferenceCommandHandler;
use App\Module\User\UseCase\Command\UpdateUserFeedLastSeenAtCommandHandler;
use App\Module\User\UseCase\Query\GetAllActiveUsersQueryHandler;
use App\Module\User\UseCase\Query\GetCalendarSubscriptionConfigQueryHandler;
use App\Module\User\UseCase\Query\GetDirectReportsQueryHandler;
use App\Module\User\UseCase\Query\GetPasswordResetTokenQueryHandler;
use App\Module\User\UseCase\Query\GetMyTeamUsersQueryHandler;
use App\Module\User\UseCase\Query\GetUserByIdQueryHandler;
use App\Module\User\UseCase\Query\GetUserBySlackMemberIdQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithBirthdaysForDatesQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithIncomingBirthdaysQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithIncomingWorkAnniversariesQueryHandler;
use App\Module\User\UseCase\Query\GetUsersWithWorkAnniversariesForDatesQueryHandler;
use App\Shared\DTO\CalendarSubscription\CalendarSubscriptionConfigDTO;
use App\Shared\DTO\InvitationDTO;
use App\Shared\DTO\PasswordResetTokenDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;
use App\Shared\Facade\UserFacadeInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class UserFacade implements UserFacadeInterface
{
    public function __construct(
        private readonly UpdateCurrentLeaveBalanceCommandHandler $updateCurrentLeaveBalanceHandler,
        private readonly GetMyTeamUsersQueryHandler $getMyTeamUsersHandler,
        private readonly GetUsersWithIncomingBirthdaysQueryHandler $getUsersWithIncomingBirthdaysHandler,
        private readonly GetUserBySlackMemberIdQueryHandler $getUserBySlackMemberIdQueryHandler,
        private readonly GetUserByIdQueryHandler $getUserByIdQueryHandler,
        private readonly GetUsersWithBirthdaysForDatesQueryHandler $getUsersWithBirthdaysForDatesHandler,
        private readonly AcceptUserInvitationCommandHandler $acceptInvitationHandler,
        private readonly GetUsersWithIncomingWorkAnniversariesQueryHandler $getUsersWithIncomingWorkAnniversariesHandler,
        private readonly GetUsersWithWorkAnniversariesForDatesQueryHandler $getUsersWithWorkAnniversariesForDatesHandler,
        private readonly ResetAbsenceBalanceCommandHandler $resetAbsenceBalanceHandler,
        private readonly GetDirectReportsQueryHandler $getDirectReportsHandler,
        private readonly UpdateThemePreferenceCommandHandler $updateThemePreferenceHandler,
        private readonly ChangePasswordCommandHandler $changePasswordHandler,
        private readonly RegenerateCalendarSubscriptionCommandHandler $regenerateCalendarSubscriptionHandler,
        private readonly UpdateSlackMemberIdCommandHandler $updateSlackMemberIdHandler,
        private readonly DisconnectSlackCommandHandler $disconnectSlackHandler,
        private readonly RemoveProfileImageCommandHandler $removeProfileImageHandler,
        private readonly CreatePasswordResetTokenCommandHandler $createPasswordResetTokenHandler,
        private readonly ResetPasswordCommandHandler $resetPasswordHandler,
        private readonly CleanupExpiredPasswordResetTokensCommandHandler $cleanupExpiredPasswordResetTokensHandler,
        private readonly GetPasswordResetTokenQueryHandler $getPasswordResetTokenHandler,
        private readonly GetAllActiveUsersQueryHandler $getAllActiveUsersHandler,
        private readonly UpdateSlackStatusSyncPreferenceCommandHandler $updateSlackStatusSyncPreferenceHandler,
        private readonly EnableTwoFactorCommandHandler $enableTwoFactorHandler,
        private readonly DisableTwoFactorCommandHandler $disableTwoFactorHandler,
        private readonly RegenerateBackupCodesCommandHandler $regenerateBackupCodesHandler,
        private readonly UpdateUserFeedLastSeenAtCommandHandler $updateUserFeedLastSeenAtHandler,
        private readonly GetCalendarSubscriptionConfigQueryHandler $getCalendarSubscriptionConfigHandler,
        private readonly UpdateCalendarSubscriptionConfigCommandHandler $updateCalendarSubscriptionConfigHandler,
    ) {
    }

    public function updateUserCurrentLeaveBalance(string $userId, int $number): void
    {
        $this->updateCurrentLeaveBalanceHandler->handle($userId, $number);
    }

    /**
     * @return UserDTO[]
     */
    public function getTeamMembersForUserId(string $userId): array
    {
        return $this->getMyTeamUsersHandler->handle($userId);
    }

    /**
     * @return UserDTO[]
     */
    public function getUsersWithIncomingBirthdays(): array
    {
        return $this->getUsersWithIncomingBirthdaysHandler->handle();
    }

    public function getUserBySlackMemberId(string $slackMemberId): ?UserDTO
    {
        return $this->getUserBySlackMemberIdQueryHandler->handle($slackMemberId);
    }

    /**
     * @return UserDTO[]
     */
    public function getUsersWithBirthdaysForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->getUsersWithBirthdaysForDatesHandler->handle($startDate, $endDate);
    }

    public function acceptUserInvitation(UserInvitationRequestDTO $invitationRequestDTO, InvitationDTO $invitationDTO): void
    {
        $this->acceptInvitationHandler->handle($invitationRequestDTO, $invitationDTO);
    }

    public function getUser(string $userId): ?UserDTO
    {
        return $this->getUserByIdQueryHandler->handle($userId);
    }

    /**
     * @return UserDTO[]
     */
    public function getUsersWithIncomingWorkAnniversaries(): array
    {
        return $this->getUsersWithIncomingWorkAnniversariesHandler->handle();
    }

    /**
     * @return UserDTO[]
     */
    public function getUsersWithWorkAnniversariesForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->getUsersWithWorkAnniversariesForDatesHandler->handle($startDate, $endDate);
    }

    public function resetAbsenceBalance(): void
    {
        $this->resetAbsenceBalanceHandler->handle();
    }

    /**
     * @return UserDTO[]
     */
    public function getDirectReports(string $managerId): array
    {
        return $this->getDirectReportsHandler->handle($managerId);
    }

    public function updateThemePreference(string $userId, ThemeEnum $theme, PaletteEnum $palette): void
    {
        $this->updateThemePreferenceHandler->handle($userId, $theme, $palette);
    }

    public function changePassword(string $userId, string $plainPassword, PasswordAuthenticatedUserInterface $user): void
    {
        $this->changePasswordHandler->handle($userId, $plainPassword, $user);
    }

    public function regenerateCalendarSubscription(string $userId): void
    {
        $this->regenerateCalendarSubscriptionHandler->handle($userId);
    }

    public function getCalendarSubscriptionConfig(string $userId): CalendarSubscriptionConfigDTO
    {
        return $this->getCalendarSubscriptionConfigHandler->handle($userId);
    }

    /**
     * @param list<string>|null $teamMemberIds
     * @param list<string>|null $holidayCalendarIds
     */
    public function updateCalendarSubscriptionConfig(string $userId, ?array $teamMemberIds, ?array $holidayCalendarIds): void
    {
        $this->updateCalendarSubscriptionConfigHandler->handle($userId, $teamMemberIds, $holidayCalendarIds);
    }

    public function updateSlackMemberId(string $userId, string $slackMemberId): void
    {
        $this->updateSlackMemberIdHandler->handle($userId, $slackMemberId);
    }

    public function disconnectSlack(string $userId): void
    {
        $this->disconnectSlackHandler->handle($userId);
    }

    public function updateSlackStatusSyncPreference(string $userId, bool $enabled): bool
    {
        return $this->updateSlackStatusSyncPreferenceHandler->handle($userId, $enabled);
    }

    public function deleteOldProfileImage(?string $currentProfileImageUrl): void
    {
        $this->removeProfileImageHandler->handle($currentProfileImageUrl);
    }

    public function createPasswordResetToken(string $email): ?string
    {
        return $this->createPasswordResetTokenHandler->handle($email);
    }

    public function resetPassword(string $token, string $plainPassword): bool
    {
        return $this->resetPasswordHandler->handle($token, $plainPassword);
    }

    public function cleanupExpiredPasswordResetTokens(): int
    {
        return $this->cleanupExpiredPasswordResetTokensHandler->handle();
    }

    public function getPasswordResetToken(string $token): ?PasswordResetTokenDTO
    {
        return $this->getPasswordResetTokenHandler->handle($token);
    }

    /**
     * @return UserDTO[]
     */
    public function getAllActiveUsers(): array
    {
        return $this->getAllActiveUsersHandler->handle();
    }

    public function enableTwoFactor(string $userId, string $plainTotpSecret): array
    {
        return $this->enableTwoFactorHandler->handle($userId, $plainTotpSecret);
    }

    public function disableTwoFactor(string $userId): void
    {
        $this->disableTwoFactorHandler->handle($userId);
    }

    public function regenerateBackupCodes(string $userId): array
    {
        return $this->regenerateBackupCodesHandler->handle($userId);
    }

    public function isTwoFactorEnabled(string $userId): bool
    {
        $user = $this->getUserByIdQueryHandler->handle($userId);

        return null !== $user && $user->isTwoFactorEnabled;
    }

    public function updateFeedLastSeenAt(string $userId, \DateTimeImmutable $seenAt): void
    {
        $this->updateUserFeedLastSeenAtHandler->handle($userId, $seenAt);
    }
}
