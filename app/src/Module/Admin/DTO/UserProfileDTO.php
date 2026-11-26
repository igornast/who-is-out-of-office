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
    public const int PROFILE_IMAGE_MAX_SIZE_MB = 15;
    public const int PROFILE_IMAGE_MAX_BYTES = self::PROFILE_IMAGE_MAX_SIZE_MB * 1_000_000;
    public const array PROFILE_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    public const string PROFILE_IMAGE_TOO_LARGE_MESSAGE = 'user.profile_image.too_large';
    public const string PROFILE_REQUEST_TOO_LARGE_MESSAGE = 'user.profile_image.request_too_large';

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
        public \DateTimeImmutable $absenceBalanceResetDay = new \DateTimeImmutable('first day of January next year midnight'),
        public ?HolidayCalendar $holidayCalendar = null,
        public ?string $subdivisionCode = null,
        #[Assert\Image(maxSize: self::PROFILE_IMAGE_MAX_SIZE_MB.'M', mimeTypes: self::PROFILE_IMAGE_MIME_TYPES, maxSizeMessage: self::PROFILE_IMAGE_TOO_LARGE_MESSAGE, uploadIniSizeErrorMessage: self::PROFILE_IMAGE_TOO_LARGE_MESSAGE)]
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
            subdivisionCode: $user->subdivisionCode,
        );
    }

    public function isRemoveProfileImageRequested(): bool
    {
        return self::REMOVE_IMAGE_FLAG === $this->removeProfileImage && null === $this->profileImageFile;
    }
}
