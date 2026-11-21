<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\DTO;

use App\Shared\DTO\UserDTO;

readonly class SlackHolidayStatusDTO
{
    public function __construct(
        public string $id,
        public string $statusText,
        public bool $isUserOnSyncedLeave,
        public UserDTO $user,
        public \DateTimeImmutable $date,
        public ?string $holidayId = null,
    ) {
    }
}
