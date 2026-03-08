<?php

declare(strict_types=1);

namespace App\Shared\Service\Ical;

use App\Shared\DTO\UserDTO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class IcalSubscriptionUrlGenerator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(env: 'ICAL_SECRET')]
        private readonly string $icalSecret,
    ) {
    }

    public function generateForUser(UserDTO $userDTO): string
    {
        return $this->urlGenerator->generate('app_api_ical_endpoint', [
            'userId' => $userDTO->id,
            'secret' => IcalHashGenerator::generateForUser($userDTO, $this->icalSecret),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
