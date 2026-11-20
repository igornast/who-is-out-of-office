<?php

declare(strict_types=1);

namespace App\Infrastructure\Slack\DTO;

use App\Shared\DTO\Holiday\PublicHolidayDTO;
use App\Shared\DTO\UserDTO;

readonly class SlackHolidayStatusCandidateDTO
{
    /**
     * @param list<PublicHolidayDTO> $holidays
     */
    public function __construct(
        public string $countryCode,
        public array $holidays,
        public UserDTO $user,
    ) {
    }
}
