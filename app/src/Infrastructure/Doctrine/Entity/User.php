<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use App\Shared\Enum\RoleEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[UniqueEntity('email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface, BackupCodeInterface
{
    use TimestampableTrait;

    /**
     * @param array<int, string>                 $roles
     * @param Collection<int, LeaveRequest>|null $leaveRequests
     * @param int[]                              $workingDays
     * @param string[]                           $backupCodes
     * @param list<string>|null                  $calendarSubscriptionTeamMemberIds
     * @param list<string>|null                  $calendarSubscriptionHolidayCalendarIds
     */
    public function __construct(
        public UuidInterface $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
        public bool $isActive = false,
        public bool $hasCelebrateWorkAnniversary = false,
        public bool $isEmailNotificationsEnabled = true,
        public bool $isTwoFactorEnabled = false,
        public int $annualLeaveAllowance = 24,
        public int $currentLeaveBalance = 24,
        public string $themePreference = 'auto',
        public string $palettePreference = 'teal',
        public ?string $plainPassword = null,
        public ?string $profileImageUrl = null,
        public ?string $subdivisionCode = null,
        public ?string $icalHashSalt = null,
        public ?string $totpSecret = null,
        public array $roles = [RoleEnum::User->value],
        public array $workingDays = [1, 2, 3, 4, 5],
        public array $backupCodes = [],
        public \DateTimeImmutable $absenceBalanceResetDay = new \DateTimeImmutable('first day of January'),
        public ?\DateTimeImmutable $birthDate = null,
        public ?\DateTimeImmutable $contractStartedAt = null,
        public ?\DateTimeImmutable $feedLastSeenAt = null,
        public ?UserSlackIntegration $slackIntegration = null,
        public ?HolidayCalendar $holidayCalendar = null,
        public ?self $manager = null,
        public ?Collection $leaveRequests = new ArrayCollection(),
        public ?array $calendarSubscriptionTeamMemberIds = null,
        public ?array $calendarSubscriptionHolidayCalendarIds = null,
    ) {
        $this->initializeTimestamps();
    }

    private ?string $decryptedTotpSecret = null;

    public function setDecryptedTotpSecret(?string $secret): void
    {
        $this->decryptedTotpSecret = $secret;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->isTwoFactorEnabled && null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (null === $this->decryptedTotpSecret) {
            return null;
        }

        return new TotpConfiguration(
            $this->decryptedTotpSecret,
            TotpConfiguration::ALGORITHM_SHA1,
            30,
            6,
        );
    }

    public function isBackupCode(string $code): bool
    {
        foreach ($this->backupCodes as $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                return true;
            }
        }

        return false;
    }

    public function invalidateBackupCode(string $code): void
    {
        $this->backupCodes = array_values(
            array_filter(
                $this->backupCodes,
                fn (string $hashedCode): bool => !password_verify($code, $hashedCode),
            ),
        );
    }

    public function getRoles(): array
    {
        return array_unique(array_merge($this->roles, [RoleEnum::User->value]));
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
        $this->decryptedTotpSecret = null;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function __toString(): string
    {
        return sprintf('%s %s (%s)', $this->firstName, $this->lastName, $this->email);
    }
}
