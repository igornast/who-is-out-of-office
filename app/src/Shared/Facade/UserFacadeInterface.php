<?php

declare(strict_types=1);

namespace App\Shared\Facade;

use App\Module\User\DTO\UserInvitationRequestDTO;
use App\Shared\DTO\CalendarSubscription\CalendarSubscriptionConfigDTO;
use App\Shared\DTO\InvitationDTO;
use App\Shared\DTO\OrganizationNodeDTO;
use App\Shared\DTO\PasswordResetTokenDTO;
use App\Shared\DTO\UserDTO;
use App\Shared\Enum\PaletteEnum;
use App\Shared\Enum\ThemeEnum;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

interface UserFacadeInterface
{
    public function updateUserCurrentLeaveBalance(string $userId, int $number): void;

    /**
     * @return UserDTO[]
     */
    public function getTeamMembersForUserId(string $userId): array;

    /**
     * @return UserDTO[]
     */
    public function getUsersWithIncomingBirthdays(): array;

    public function getUserBySlackMemberId(string $slackMemberId): ?UserDTO;

    /**
     * @return UserDTO[]
     */
    public function getUsersWithBirthdaysForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array;

    /**
     * @return UserDTO[]
     */
    public function getUsersWithIncomingWorkAnniversaries(): array;

    /**
     * @return UserDTO[]
     */
    public function getUsersWithWorkAnniversariesForDates(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array;

    public function acceptUserInvitation(UserInvitationRequestDTO $invitationRequestDTO, InvitationDTO $invitationDTO): void;

    public function getUser(string $userId): ?UserDTO;

    public function resetAbsenceBalance(): void;

    /**
     * @return UserDTO[]
     */
    public function getDirectReports(string $managerId): array;

    public function updateThemePreference(string $userId, ThemeEnum $theme, PaletteEnum $palette): void;

    public function changePassword(string $userId, string $plainPassword, PasswordAuthenticatedUserInterface $user): void;

    public function regenerateCalendarSubscription(string $userId): void;

    public function getCalendarSubscriptionConfig(string $userId): CalendarSubscriptionConfigDTO;

    /**
     * @param list<string>|null $teamMemberIds
     * @param list<string>|null $holidayCalendarIds
     */
    public function updateCalendarSubscriptionConfig(string $userId, ?array $teamMemberIds, ?array $holidayCalendarIds): void;

    public function updateSlackMemberId(string $userId, string $slackMemberId): void;

    public function disconnectSlack(string $userId): void;

    public function updateSlackStatusSyncPreference(string $userId, bool $enabled): bool;

    public function deleteOldProfileImage(?string $currentProfileImageUrl): void;

    public function createPasswordResetToken(string $email): ?string;

    public function resetPassword(string $token, string $plainPassword): bool;

    public function cleanupExpiredPasswordResetTokens(): int;

    public function getPasswordResetToken(string $token): ?PasswordResetTokenDTO;

    /**
     * @return UserDTO[]
     */
    public function getAllActiveUsers(): array;

    /**
     * @return list<OrganizationNodeDTO>
     */
    public function getOrganizationTree(): array;

    /**
     * @return string[] Plain-text recovery codes (shown to user once)
     */
    public function enableTwoFactor(string $userId, string $plainTotpSecret): array;

    public function disableTwoFactor(string $userId): void;

    /**
     * @return string[] Plain-text recovery codes (shown to user once)
     */
    public function regenerateBackupCodes(string $userId): array;

    public function isTwoFactorEnabled(string $userId): bool;

    public function updateFeedLastSeenAt(string $userId, \DateTimeImmutable $seenAt): void;
}
