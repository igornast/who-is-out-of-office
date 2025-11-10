<?php

declare(strict_types=1);

namespace App\Shared\DTO\Holiday;

use App\Shared\DTO\UserDTO;

readonly class UserPublicHolidaysDTO
{
    /**
     * @param PublicHolidayDTO[] $holidays
     */
    public function __construct(
        public UserDTO $user,
        public array $holidays,
    ) {
    }

    public static function createFromGroupedArray(array $data): self
    {
        $userData = $data[0];
        $userData['id'] = $userData['user_id'];

        return new self(
            user: UserDTO::fromArray($userData),
            holidays: array_map(fn (array $itemData) => PublicHolidayDTO::fromArray($itemData), $data),
        );
    }
}
