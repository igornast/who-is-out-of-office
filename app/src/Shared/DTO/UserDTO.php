<?php

declare(strict_types=1);

namespace App\Shared\DTO;

use App\Infrastructure\Doctrine\Entity\User;

class UserDTO
{
    /**
     * @param array<int, string> $roles
     * @param list<string>|null  $calendarSubscriptionTeamMemberIds
     * @param list<string>|null  $calendarSubscriptionHolidayCalendarIds
     */
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public array $roles,
        public array $workingDays,
        public int $annualLeaveAllowance,
        public int $currentLeaveBalance,
        public bool $isActive,
        public \DateTimeImmutable $createdAt,
        public ?string $password = null,
        public ?string $profileImageUrl = null,
        public ?string $slackMemberId = null,
        public ?string $calendarCountryCode = null,
        public ?string $subdivisionCode = null,
        public ?bool $hasCelebrateWorkAnniversary = false,
        public bool $isEmailNotificationsEnabled = true,
        public ?\DateTimeImmutable $birthDate = null,
        public ?\DateTimeImmutable $contractStartedAt = null,
        public ?\DateTimeImmutable $feedLastSeenAt = null,
        public \DateTimeImmutable $absenceBalanceResetDay = new \DateTimeImmutable('first day of January'),
        public ?string $managerId = null,
        public string $themePreference = 'auto',
        public string $palettePreference = 'teal',
        public ?string $icalHashSalt = null,
        public bool $slackStatusSyncEnabled = true,
        public bool $isTwoFactorEnabled = false,
        public ?array $calendarSubscriptionTeamMemberIds = null,
        public ?array $calendarSubscriptionHolidayCalendarIds = null,
    ) {
    }

    public function getFullName(): string
    {
        return sprintf('%s %s', $this->firstName, $this->lastName);
    }

    public static function fromEntity(User $user): UserDTO
    {
        return new self(
            id: $user->id->toString(),
            firstName: $user->firstName,
            lastName: $user->lastName,
            email: $user->email,
            roles: $user->roles,
            workingDays: $user->workingDays,
            annualLeaveAllowance: $user->annualLeaveAllowance,
            currentLeaveBalance: $user->currentLeaveBalance,
            isActive: $user->isActive,
            createdAt: $user->getCreatedAt(),
            password: $user->password,
            profileImageUrl: $user->profileImageUrl,
            slackMemberId: $user->slackIntegration?->slackMemberId,
            calendarCountryCode: $user->holidayCalendar->countryCode ?? null,
            subdivisionCode: $user->subdivisionCode,
            hasCelebrateWorkAnniversary: $user->hasCelebrateWorkAnniversary,
            isEmailNotificationsEnabled: $user->isEmailNotificationsEnabled,
            birthDate: $user->birthDate,
            contractStartedAt: $user->contractStartedAt,
            feedLastSeenAt: $user->feedLastSeenAt,
            absenceBalanceResetDay: $user->absenceBalanceResetDay,
            managerId: $user->manager?->id->toString(),
            themePreference: $user->themePreference,
            palettePreference: $user->palettePreference,
            icalHashSalt: $user->icalHashSalt,
            slackStatusSyncEnabled: $user->slackIntegration?->slackStatusSyncEnabled ?? true,
            isTwoFactorEnabled: $user->isTwoFactorEnabled,
            calendarSubscriptionTeamMemberIds: $user->calendarSubscriptionTeamMemberIds,
            calendarSubscriptionHolidayCalendarIds: $user->calendarSubscriptionHolidayCalendarIds,
        );
    }

    public static function fromArray(array $data): UserDTO
    {
        return new self(
            id: $data['id'],
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            email: $data['email'],
            roles: json_decode($data['roles'], true, flags: JSON_THROW_ON_ERROR),
            workingDays: json_decode($data['working_days'], true, flags: JSON_THROW_ON_ERROR),
            annualLeaveAllowance: $data['annual_leave_allowance'],
            currentLeaveBalance: $data['current_leave_balance'],
            isActive: (bool) $data['is_active'],
            createdAt: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['created_at']),
            profileImageUrl: $data['profile_image_url'],
            subdivisionCode: $data['subdivision_code'] ?? null,
            hasCelebrateWorkAnniversary: (bool) ($data['celebrate_work_anniversary'] ?? false),
            isEmailNotificationsEnabled: (bool) ($data['is_email_notifications_enabled'] ?? true),
            birthDate: isset($data['birth_date']) ? \DateTimeImmutable::createFromFormat('Y-m-d', $data['birth_date']) : null,
            contractStartedAt: isset($data['contract_started_at']) ? \DateTimeImmutable::createFromFormat('Y-m-d', $data['contract_started_at']) : null,
            feedLastSeenAt: isset($data['feed_last_seen_at']) ? \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['feed_last_seen_at']) : null,
            absenceBalanceResetDay: \DateTimeImmutable::createFromFormat('Y-m-d', $data['absence_balance_reset_day']),
            managerId: $data['manager_id'] ?? null,
            themePreference: $data['theme_preference'] ?? 'auto',
            palettePreference: $data['palette_preference'] ?? 'teal',
            calendarCountryCode: $data['calendar_country_code'] ?? null,
            icalHashSalt: $data['ical_hash_salt'] ?? null,
            slackStatusSyncEnabled: (bool) ($data['slack_status_sync_enabled'] ?? true),
            isTwoFactorEnabled: (bool) ($data['is_two_factor_enabled'] ?? false),
            calendarSubscriptionTeamMemberIds: isset($data['calendar_subscription_team_member_ids']) && is_string($data['calendar_subscription_team_member_ids'])
                ? json_decode($data['calendar_subscription_team_member_ids'], true, flags: JSON_THROW_ON_ERROR)
                : ($data['calendar_subscription_team_member_ids'] ?? null),
            calendarSubscriptionHolidayCalendarIds: isset($data['calendar_subscription_holiday_calendar_ids']) && is_string($data['calendar_subscription_holiday_calendar_ids'])
                ? json_decode($data['calendar_subscription_holiday_calendar_ids'], true, flags: JSON_THROW_ON_ERROR)
                : ($data['calendar_subscription_holiday_calendar_ids'] ?? null),
        );
    }
}
