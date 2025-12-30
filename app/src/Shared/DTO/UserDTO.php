<?php

declare(strict_types=1);

namespace App\Shared\DTO;

use App\Infrastructure\Doctrine\Entity\User;

class UserDTO
{
    /**
     * @param array<int, string> $roles
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
        public bool $hasCelebrateWorkAnniversary = false,
        public \DateTimeImmutable $createdAt,
        public ?string $password = null,
        public ?string $profileImageUrl = null,
        public ?string $slackMemberId = null,
        public ?string $calendarCountryCode = null,
        public ?\DateTimeImmutable $birthDate = null,
        public ?\DateTimeImmutable $contractStartedAt = null,
    ) {
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
            hasCelebrateWorkAnniversary: $user->hasCelebrateWorkAnniversary,
            createdAt: $user->getCreatedAt(),
            password: $user->password,
            profileImageUrl: $user->profileImageUrl,
            slackMemberId: $user->slackIntegration?->slackMemberId,
            calendarCountryCode: $user->holidayCalendar->countryCode ?? null,
            birthDate: $user->birthDate,
            contractStartedAt: $user->contractStartedAt,
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
            workingDays: json_decode($data['working_days'], false, flags: JSON_THROW_ON_ERROR),
            annualLeaveAllowance: $data['annual_leave_allowance'],
            currentLeaveBalance: $data['current_leave_balance'],
            isActive: (bool) $data['is_active'],
            hasCelebrateWorkAnniversary: (bool) ($data['celebrate_work_anniversary'] ?? false),
            createdAt: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['created_at']),
            profileImageUrl: $data['profile_image_url'],
            birthDate: isset($data['birth_date']) ? \DateTimeImmutable::createFromFormat('Y-m-d', $data['birth_date']) : null,
            contractStartedAt: isset($data['contract_started_at']) ? \DateTimeImmutable::createFromFormat('Y-m-d', $data['contract_started_at']) : null,
        );
    }
}
