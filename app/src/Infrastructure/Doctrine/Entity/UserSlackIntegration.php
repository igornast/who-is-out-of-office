<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[UniqueEntity('slackMemberId')]
class UserSlackIntegration
{
    public function __construct(
        public User $user,
        public string $slackMemberId,
    ) {
    }
}
