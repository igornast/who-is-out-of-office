<?php

declare(strict_types=1);

namespace App\Shared\Service\Ical;

use App\Shared\DTO\UserDTO;

class IcalHashGenerator
{
    public static function generateForUser(UserDTO $userDTO, string $secret): string
    {
        $payload = null !== $userDTO->icalHashSalt
            ? $userDTO->id.'|'.$userDTO->icalHashSalt
            : $userDTO->id.'|'.$userDTO->createdAt->format('c');

        return hash_hmac('sha256', $payload, $secret);
    }
}
