<?php

declare(strict_types=1);

namespace App\Infrastructure\Ical\Service;

use App\Shared\DTO\UserDTO;
use App\Shared\Service\Ical\IcalHashGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class CalendarRequestVerifier
{
    public function __construct(
        #[Autowire(env: 'ICAL_SECRET')]
        private readonly string $icalSecret,
    ) {
    }

    public function isValid(UserDTO $userDTO, string $secret): bool
    {
        return hash_equals(IcalHashGenerator::generateForUser($userDTO, $this->icalSecret), $secret);
    }
}
