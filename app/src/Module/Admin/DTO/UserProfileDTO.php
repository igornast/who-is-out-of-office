<?php

declare(strict_types=1);

namespace App\Module\Admin\DTO;

use App\Infrastructure\Doctrine\Entity\HolidayCalendar;
use App\Infrastructure\Doctrine\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class UserProfileDTO
{
    public const string REMOVE_IMAGE_FLAG = '1';

    /**
     * @param int[] $workingDays
     */
    public function __construct(
        #[Assert\NotBlank]
        public string $firstName = '',
        #[Assert\NotBlank]
        public string $lastName = '',
        #[Assert\NotBlank]
        public array $workingDays = [],
        public bool $hasCelebrateWorkAnniversary = false,
        public bool $isEmailNotificationsEnabled = true,
        public ?string $removeProfileImage = null,
        public ?\DateTimeImmutable $birthDate = null,
        public ?\DateTimeImmutable $contractStartedAt = null,
        public \DateTimeImmutable $absenceBalanceResetDay = new \DateTimeImmutable('first day of January'),
        public ?HolidayCalendar $holidayCalendar = null,
        #[Assert\Image(maxSize: '2M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])]
        public ?UploadedFile $profileImageFile = null,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            firstName: $user->firstName,
            lastName: $user->lastName,
            workingDays: $user->workingDays,
            hasCelebrateWorkAnniversary: $user->hasCelebrateWorkAnniversary,
            isEmailNotificationsEnabled: $user->isEmailNotificationsEnabled,
            birthDate: $user->birthDate,
            contractStartedAt: $user->contractStartedAt,
            absenceBalanceResetDay: $user->absenceBalanceResetDay,
            holidayCalendar: $user->holidayCalendar,
        );
    }

    public function isRemoveProfileImageRequested(): bool
    {
        return self::REMOVE_IMAGE_FLAG === $this->removeProfileImage && null === $this->profileImageFile;
    }
}
