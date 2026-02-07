<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use App\Infrastructure\Traits\TimestampableTrait;
use App\Shared\Enum\RoleEnum;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[UniqueEntity('email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampableTrait;

    /**
     * @param array<int, string>                 $roles
     * @param Collection<int, LeaveRequest>|null $leaveRequests
     * @param int[]                              $workingDays
     */
    public function __construct(
        public UuidInterface $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
        public array $roles = [RoleEnum::User->value],
        public array $workingDays = [1, 2, 3, 4, 5],
        public int $annualLeaveAllowance = 24,
        public int $currentLeaveBalance = 24,
        public bool $isActive = false,
        public bool $hasCelebrateWorkAnniversary = false,
        public ?string $plainPassword = null,
        public ?string $profileImageUrl = null,
        public ?\DateTimeImmutable $birthDate = null,
        public ?\DateTimeImmutable $contractStartedAt = null,
        public ?UserSlackIntegration $slackIntegration = null,
        public \DateTimeImmutable $absenceBalanceResetDay = new \DateTimeImmutable('first day of January'),
        public ?HolidayCalendar $holidayCalendar = null,
        public ?Collection $leaveRequests = new ArrayCollection(),
    ) {
        $this->initializeTimestamps();
    }

    public function getRoles(): array
    {
        return array_unique(array_merge($this->roles, [RoleEnum::User->value]));
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
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
